# v2b — Admin rewrite (Filament)

Status: **complete. Decisions approved 2026-09-12; v2b.0 to v2b.7 done (2026-09-14).**
Written 2026-09-12 after the v2a phases closed. Decisions marked *(proposed)*
were approved as written. It complements `ARCHITECTURE.md` (§6 defers the
admin to v2b, §12 assigns it the SEO management UI) and
`01_STACK_SPECIFICATION.md` (rappasoft tables "frozen with the admin;
removed in v2b").

## 1. Scope

v2b replaces the legacy admin (Blade + UIkit + jQuery + CKEditor CDN +
rappasoft/laravel-livewire-tables) with a Filament panel, keeps every
feature the shop actually uses, drops what is dead, and gives the storefront
SEO fields their management UI. The admin is not part of the skin overlay
(`ARCHITECTURE.md §4`): it is core code, one implementation for every
installation, with installation behaviour behind config flags.

Out of scope for v2b, still deferred: Vue configurator island, Meilisearch /
Typesense beyond scaffold, Italian fiscal exporters, MJML newsletter pipeline.

## 2. What the analysis found

Measured on the code as of commit `4baebf9` (three read-only sweeps of
routes, controllers, views, Livewire tables, models and schema).

### 2.1 Size and shape

| Surface | Count |
|---|---|
| Admin routes (`routes/web.php:128-214`) | 70 route declarations, ~150 endpoints once resources expand |
| Controllers `app/Http/Controllers/Admin*.php` | 35 files, 2 481 lines |
| Livewire tables `app/Http/Livewire/Admin*.php` | 15 files, 963 lines (14 rappasoft, 1 plain Livewire) |
| Views `resources/views/admin/**` | 85 files, ~4 770 lines |
| Inline Italian copy | ~690 strings in Blade + ~60 in table classes, zero `__()` |
| Static analysis | admin excluded from larastan (`phpstan.neon:15-17`) and from pint |
| Tests | none |

Runtime dependencies loaded per request from CDNs: Bootstrap 5.3 alpha,
UIkit pinned to `@latest` (unversioned), jQuery 3.6, CKEditor 5 (35.1.0,
loaded in eight views), Google Fonts. Local: `public/css/admin.css`,
`colors.css` (Italian-named utilities), `jquery-select-filter`. Alpine is
not used; Vite assets are loaded alongside everything else.

### 2.2 Feature inventory

Legend: **works** = usable today; **partial** = usable with bugs; **stub** =
menu entry with an empty or title-only page; **dead** = routed but no
method, or code with no route.

| Area | State | Notes for the rewrite |
|---|---|---|
| Login / logout | works | Plain `Auth::attempt`, role checked only by the route group; "recover password" form posts to a template leftover. Footer carries the client's legal name and VAT (`admin/auth/login.blade.php:16`). |
| Dashboard | partial | KPI cards hard-coded to `0`; shows last import run and last 10 import log rows. |
| Messages, quotations | works | Read-only detail, `read_at` marking, unread badges via `AdminDataComposer`. Quotation delete from the table. |
| Orders | partial | Status machine in `AdminOrdersController::update` (see §4.3), resend mail, order file upload/delete (media `order_files`). Shipping card shows the billing address. |
| Customers | partial | Edit customer + billing + shipping addresses; the conditional-field and fiscal-code JS never runs (section never yielded); "create" posts to a non-existent route; province list hard-coded (110 entries). |
| Products | works | Table with filters, bulk status/category change, XLSX export (no column mapping); edit with CKEditor description, categories accordion, inline variants, "set main variant" AJAX; create with magic defaults `color_id 542`, `size_id 1` and auto-increment read via `SHOW TABLE STATUS`. Source select hard-codes supplier names. |
| Variants | partial | Prices repeater, stock and next-stock, images with sortable grid whose "save order" does nothing; `update_prices` route is a no-op that flashes success; `print_r` debug dump at the end of edit/view. |
| Categories | partial | CRUD, icon uploads, drag-and-drop order of roots and of products in a category; sub-category order route points to a missing method. `Cache::forget('categories')` after writes. |
| Colours, families, sizes, size types | partial | Index + create only; no edit, no delete; four lists are view-only dead ends. |
| Attributes | works | Index, create, edit, delete without confirm. |
| Brands | partial | Not a table: grouped from `products.brand`; only action is uploading a logo to `public/img/brands`. |
| Pages (CMS) | works | Full form incl. product filters and AJAX product picker; `store` returns a wrong view; no validation (`$request->toArray()`). |
| Blog articles, tags | works | Validated CRUD, the cleanest area. |
| Contents, profile, reports (3), logs (5) | stub | 6 title-only views, 5 zero-byte log views, 1 missing view (`analytics_sell`). No queries behind any report. |
| Redirects | stub | Lists registered routes; **no redirect table exists** anywhere in the schema. |
| Sitemap | partial | Regenerates `public/sitemap.xml` on every page view, then prints it with a broken echo. Command `app:GenerateSitemap` exists and is not scheduled. |
| Imports: manage | works | Dispatches `ImportProductsJob` / `ImportPrintingsJob` with flags, shows queue counters and history, and exposes an Artisan runner (whitelisted to three commands). |
| Imports: categories map | works | Alias ⇄ category mapping with jquery-select-filter. |
| Imports: colours map, rules, errors, price tiers, print markups, raw supplier browsers | stub / dead | Empty views or duplicates; one raw browser runs a **synchronous curl to the supplier API inside the web request**, one product per request; a v1 supplier import controller (368 lines) is unrouted. |
| Support | dead | Calls an external ticket API for a list; create does nothing. |

Dead code confirmed: `App\Models\Admin` (no `admins` table, no guard),
`LegacyApiController`, the v1 supplier import controller, several unrouted
methods, four duplicate legacy views.

### 2.3 Data model facts that shape the rewrite

- **SEO**: only `slug`, `seo_title`, `seo_description` exist on products,
  categories, pages, blog articles. Missing versus `ARCHITECTURE.md §12`:
  `canonical_url`, `noindex`, `og_title`, `og_description`, `og_image`, media
  `alt`, slug history. Brands have no table at all.
- **Redirects**: nothing. Slug regeneration commands exist with no safety net.
- **Casts and enums**: no model except `User` declares `$casts`. Order
  status, payment status, payment method, page filter type and customer type
  are DB enums mirrored by static label arrays (`Order::$status_names` etc.).
- **Soft deletes**: `deleted_at` exists on 14 tables; only `Quotation` uses
  `SoftDeletes`. Products, variants, categories are hard-deleted.
- **Media**: `ProductVariant` owns the only collection (`image`, conversion
  `thumb` 150×150, non-queued); `Order` stores `order_files` without a
  registered collection; `Product` has no media (cover derives from the main
  variant). No `alt`.
- **Auth**: one `web` guard, roles `admin`/`customer`, **no permission
  defined**; the only gate is `role:admin`.
- **Import-only tables**: `raw_*` (≈57 tables), `normalized_*`, `meta_*`,
  `*_import_aliases` (three of them have models but no table in the schema),
  `import_logs`, `legacy_stock_export`, `product_markups`,
  `normalized_rules_*`. These are the connector-package candidates of
  `pre-public-repo` task 1.
- **Larastan**: 433 baseline entries; `Order` (21), `Page` (10), `Product`
  and `ProductVariant` (9 each) are the models the admin will touch most.

### 2.4 Compatibility check (verified with composer on 2026-09-12)

| Option | Result |
|---|---|
| `filament/filament ^4.13` + `filament/spatie-laravel-media-library-plugin ^4.13` | resolves on the current stack (Laravel 13.31, Livewire 3.8, PHP 8.4, Tailwind 4): 30 new packages, 0 updates |
| `filament/filament ^5.8` | requires Livewire ^4.1; blocked by `rappasoft/laravel-livewire-tables` (Livewire ^3) and by the storefront Livewire 3 components |

The storefront Livewire surface is small (6 components, 5 views, ~30 `wire:`
directives), so a Livewire 4 move is feasible but it is a separate upgrade
with its own parity check.

## 3. Decisions

Each item states the recommendation; alternatives are listed where the
choice is not obvious.

1. **Filament 4 now, Filament 5 later** *(proposed)*. Start on 4.x with
   Livewire 3, which touches nothing in the storefront. Plan the Livewire 4
   + Filament 5 upgrade as the last step of v2b, after rappasoft is removed
   (§5, step 7). Alternative: upgrade Livewire first and build on 5 from
   day one; rejected because it couples the admin rewrite to a storefront
   upgrade and the go-live of the first installations.
2. **One panel, same guard** *(proposed)*. Panel id `admin`, path `/admin`,
   guard `web`, access = `User::hasRole('admin')` via `FilamentUser`. No
   second guard, `App\Models\Admin` deleted. Route names `admin.*` are
   internal (not part of the skin contract) and may change; the URL `/admin`
   stays.
3. **Permissions become real** *(proposed)*. Seed a small permission set in
   `CoreSeeder` (`catalog.manage`, `orders.manage`, `content.manage`,
   `imports.run`, `settings.manage`) and give them to `admin`. Resources use
   policies checking permissions, not the role, so installations can add
   restricted roles (e.g. "editor") without code. No Shield plugin.
4. **Parity by inventory, not by pixel.** "Works" and "partial" rows of §2.2
   are rebuilt; "stub" and "dead" rows are dropped unless listed in §4 as
   new features. The order status machine and the product/variant pricing
   side effects are reproduced exactly (they have mail and price
   consequences) and get tests before the legacy code is deleted.
5. **Copy in lang files.** All admin strings live in `lang/it/admin.php`
   (Filament's own strings come translated). No client identifiers: the
   login footer reads `config('brand')`.
6. **SEO data model before the UI.** One migration adds the missing fields
   (§4.5) and a `legacy_redirects` table; models get casts, backed enums and
   `SoftDeletes` where the column exists. This is v2b step 0 and is
   storefront-visible, so it ships with storefront rendering of the new
   fields and tests.
7. **Brands get a table** *(proposed)*. `brands` (`name`, `slug`, `logo`,
   SEO fields) with `products.brand_id`, keeping `products.brand` as a
   denormalised label during transition. Alternative: keep the grouped
   pseudo-model; rejected because brand pages need SEO fields and a logo
   that is not a file in `public/img`.
8. **Imports through the contracts.** The Filament import page dispatches the
   existing jobs and reads `ImportConnectors` (Phase 5). No supplier name
   in the core admin: connector labels come from `ImportConnector::source()`
   and pages for raw data, category/colour maps and markups are provided by
   the connector packages once extracted (§6). Until extraction, the core
   keeps only "manage" (dispatch, history, log viewer) and "category aliases".
9. **No Artisan runner in the UI.** Replace with explicit actions (dispatch
   jobs, restart queue, regenerate sitemap, reindex search) each behind
   `imports.run`.
10. **Sitemap and search index are scheduled, not clicked.** Schedule
    `app:GenerateSitemap` nightly; the admin page shows the last generation
    and offers a "regenerate now" action.
11. **Rich text**: Filament's `RichEditor` replaces CKEditor. Existing HTML
    content (products, categories, pages, blog) is kept as is; the storefront
    `.prose` rules already normalise it.
12. **Reports**: implement the three reports as Filament widgets with real
    queries (orders per month and by status, quotation and message volume,
    top products by ordered quantity). "Logs" pages are replaced by a single
    import log resource plus the application log left to the hosting.
13. **Support tickets**: dropped from the core. If an installation needs it,
    it is a plugin behind `features.support`.

## 4. Target design

### 4.1 Layout

```
app/Filament/
  AdminPanelProvider.php            panel: id admin, path /admin, guard web, brand from config/brand.php
  Resources/Catalog/{Product,ProductVariant,Category,Brand,ProductColor,ProductColorFamily,ProductSize,ProductSizeType,ProductAttribute}Resource
  Resources/Sales/{Order,Quotation,Message,Customer}Resource
  Resources/Content/{Page,HomeSlide,BlogArticle,BlogTag,Redirect}Resource
  Resources/System/{User,ImportLog}Resource
  Pages/{Dashboard,Imports,Sitemap}
  Widgets/{SalesOverview,OrdersByStatus,ContactsVolume,TopProducts,LastImport}
app/Actions/Orders/TransitionOrder.php      status machine extracted from the legacy controller
app/Actions/Catalog/{SetMainVariant,SyncProductCategories,CreateProductWithVariant}.php
app/Enums/{OrderStatus,PaymentStatus,PaymentMethod,CustomerType,PageFilterType}.php
app/Policies/*                                permission-based
lang/it/admin.php                            every admin string
database/migrations/2026_xx_seo_fields.php, ..._brands.php, ..._legacy_redirects.php
tests/Feature/Admin/*                        one test class per resource, seeded by DemoSeeder
```

Navigation groups mirror the legacy menu that people know: **Vendite**
(ordini, preventivi, messaggi, clienti), **Catalogo**, **Contenuti**,
**Import**, **Sistema**. Badges for unread messages, unread quotations and
orders in `requested` status.

### 4.2 Resources and forms (parity mapping)

| Resource | Form | Table | Actions |
|---|---|---|---|
| Product | tabs: Dati (sku read-only after create, name, brand relation, description RichEditor, flags green/promo/bestseller, forced_status), Categorie (tree checkbox, parent auto-added), Varianti (relation manager), SEO (§4.5) | id, sku, source, active, cover, name, categories; filters status/source/category/brand; bulk: activate, deactivate, assign category, export XLSX with mapped columns | set main variant (recomputes prices and cover), duplicate |
| ProductVariant | relation manager on Product + own edit page: color, size, active, sale, stock, next stock date/qty, prices repeater (from_quantity, price, original_price), attributes key/value, media (Spatie plugin, reorderable, alt), printing options read-only | — | reorder images persists (fixes the dead button) |
| Category | parent, name, slug, icon/icon_rev media, description, extra_text, SEO | tree ordered by position; reorder roots and children (fixes the missing route); products order relation manager | delete cascades as today, cache flush |
| Brand | name, slug, logo media, SEO | table with product counts | — |
| Colour / family / size / size type / attribute | simple forms, full CRUD with delete confirm and "in use" guard | — | — |
| Order | read-only header + customer + addresses (shipping shows shipping); editable status, payment status (method only in draft/requested), tracking; items with articles and printings; order files media | id, customer, total, status, payment, dates; filters status/payment/method/date | transition (mails exactly as today), resend confirmation, print/export |
| Quotation / Message | read-only infolists, `read_at` on view | filters read/unread | delete (quotation) |
| Customer | user link, type (enum drives visible fiscal fields, server-side), fiscal fields, two addresses with "copy billing" | filters by type/activity | create with user + role customer |
| Page | title, slug, cover, navbar, position, text/extra RichEditor, CTA, product listing block (filter type enum, category select, flags, created-after date picker, explicit product picker), SEO | — | preview link |
| HomeSlide | new resource for `content_home_slides` (today edited only in DB) | reorderable | — |
| BlogArticle / BlogTag | as legacy, validated | — | — |
| Redirect | from path, to path, code (301/410), hits | search; import CSV | — |
| User | name, email, password, roles | filter by role | — |
| ImportLog | read-only, grouped by `import_id` | filters context/type; auto-refresh | — |

### 4.3 Order transitions (must stay identical)

From `AdminOrdersController::update` (legacy lines 20-58):

- `payment_method` editable only while status is `draft` or `requested`.
- `payment_status` → `paid`: if status is `draft`/`requested`, status becomes
  `paid`; mail `order_paid_user`. Nothing else in the same save.
- `tracking_code` changed: mail `order_sent`.
- Otherwise, status changed: `cancelled` → `order_cancelled`, any other →
  `order_updated`.
- One mail per save, through `Order::send_notification` → `TransactionalMailer`.

`TransitionOrder` action encapsulates this and is unit-tested with the
`array` mail driver before the legacy controller goes.

### 4.4 Catalogue side effects (must stay identical)

- Setting a main variant recomputes `variants_min_price`,
  `variants_max_price`, `cover_url` (`Product::set_main_variant`).
- Deleting or deactivating a variant re-elects a main variant.
- Category sync always adds the parent of a selected child.
- Product creation creates one variant with a first price tier and a cover.
  Magic ids (`color_id 542`, `size_id 1`) are replaced by a required colour
  select and `mercatura.catalog.one_size_id`.
- Every product save reindexes through Scout (Algolia in production): bulk
  actions must run in chunks or with `withoutSyncingToSearch` + a final
  `scout:import` for the affected ids.
- `Cache::forget('categories')` (and the catalog filter caches of
  `CatalogFilterOptions`) after category or product writes.

### 4.5 SEO and redirects (the v2a debt v2b pays)

Migration on products, categories, pages, blog_articles, brands:
`meta_title`, `meta_description` (rename from `seo_*` with a compatibility
accessor), `canonical_url` nullable, `noindex` boolean default false,
`og_title`, `og_description`, `og_image` nullable. Media `alt` in
`custom_properties` (Spatie plugin supports it). `legacy_redirects`
(`from_path` unique, `to_path`, `status_code`, `hits`, `last_hit_at`) served
by a middleware placed after the router's 404, plus automatic rows when a
slug changes from the admin. Storefront components `seo.meta` and
`seo/site-jsonld` read the new fields with the existing fallbacks; sitemap
skips `noindex`. Tests: rendering of each field, redirect middleware,
slug-change redirect.

### 4.6 Tests

Filament resources are Livewire components: `Livewire::test(ListProducts::class)`
and `livewire()->fillForm()->call('create')` cover forms and tables. One test
class per resource, seeded by `DemoSeeder` inside a transaction (the
`DemoSeederTest` pattern). Golden tests for §4.3 and §4.4. Larastan and pint
enforced on `app/Filament` from the first commit (the exclusion in
`phpstan.neon` is removed when the legacy admin is deleted).

## 5. Phases and stop criteria

Numbered v2b.0 … v2b.7. Each is a commit range that keeps `composer test`,
larastan and `skin-check demo` green; the legacy admin stays reachable at
`/admin-legacy` until step 7 so that nothing is lost during the transition.

| Step | Content | Stop criterion |
|---|---|---|
| **v2b.0 Model hardening** *(done 2026-09-13, see deviations below the table)* | Casts, backed enums (`OrderStatus`, `PaymentStatus`, `PaymentMethod`, `CustomerType`, `PageFilterType`) with the same DB values, `SoftDeletes` where `deleted_at` exists, typed relations, `Message::$fillable` fix, `brands` table + backfill, SEO fields migration + storefront rendering, `legacy_redirects` + middleware, permissions seeded, `App\Models\Admin` and other dead code deleted. No Filament yet. | Storefront unchanged in tests and Lighthouse; larastan baseline for `Order`/`Page`/`Product`/`ProductVariant` shrinks; redirect and SEO tests green. |
| **v2b.1 Panel skeleton** *(done 2026-09-12; order chosen: skeleton before model hardening so the panel is visible early)* | `filament/filament ^4.13` + media plugin (dependency addition to approve), `AdminPanelProvider`, login with brand, dashboard with `LastImport` widget and counters, `lang/it/admin.php`, legacy admin moved to `/admin-legacy`. Read-only resources for orders, quotations, messages, customers. | `/admin` logs in with `admin@example.com` on demo data and shows the four lists; legacy still works at `/admin-legacy`. |
| **v2b.2 Sales** *(done 2026-09-13; customer creation deferred to v2b.3 with the user resource)* | Order transitions (`TransitionOrder` + tests), order files, resend mail, customer edit with addresses and enum-driven fiscal fields, quotation delete, badges. | Every legacy order/customer action has a Filament equivalent covered by a test; mails asserted with the `array` driver. |
| **v2b.3 Catalogue** *(done 2026-09-13; "duplicate product" and per-image alt left out, see deviations)* | Product, variant, category, brand, colour, family, size, size type, attribute resources; relation managers; media with alt and persisted order; bulk actions; XLSX export with columns; `SetMainVariant`, `SyncProductCategories`, `CreateProductWithVariant` actions + tests; cache flushes; Scout-aware bulk. | Demo catalogue fully editable from Filament; §4.4 golden tests green; no supplier name in the core admin. |
| **v2b.4 Content and SEO UI** *(done 2026-09-13)* | Page (with product block and picker), HomeSlide, BlogArticle/Tag, Redirect resources; SEO tab on product, category, page, brand, blog; sitemap page + nightly schedule; reports widgets with real queries; ImportLog resource. | SEO fields editable everywhere §4.5 lists; sitemap generated by schedule; three report widgets show demo numbers. |
| **v2b.5 Imports** *(done 2026-09-13; connector-specific pages left to the packages)* | Imports page: dispatch products/printings with flags, queue counters, history, per-run log; category aliases resource; explicit actions (queue restart, reindex, sitemap). Everything through `ImportConnectors`; connector-specific pages left to the packages (§6). | On the demo (connectors off) the page shows "no connector enabled"; with a flag on, the legacy jobs run unchanged. |
| **v2b.6 Removal** *(done 2026-09-14)* | Delete `app/Http/Controllers/Admin*`, `app/Http/Livewire/Admin*`, `resources/views/admin/**`, `public/css/{admin,colors,frontend,uikit}.css`, `public/js/*`, `config/livewire-tables.php`; `composer remove rappasoft/laravel-livewire-tables`; remove the phpstan/pint exclusions; docs. | No UIkit/jQuery/CDN anywhere in the repo (`StorefrontIntegrityTest` extended to the whole repo); `/admin-legacy` gone. |
| **v2b.7 Livewire 4 + Filament 5** *(done 2026-09-14 on the user's request; Rector script made no code change, config/livewire.php rebuilt on the v4 default with our overrides)* | Upgrade Livewire, adapt the 6 storefront components, move to Filament 5. | Storefront tests, Lighthouse and the Filament tests unchanged. |

Deviations recorded while doing v2b.0:

- `seo_title` / `seo_description` keep their column names: the legacy admin
  writes them until v2b.6, so a rename would have broken it; the new
  columns are `canonical_url`, `noindex`, `og_title`, `og_description`,
  `og_image` (`App\Models\Concerns\HasSeoFields`).
- Enums exist (`App\Enums`) and are checked against the legacy label arrays
  and the database enums by `EnumsTest`, but they are **not cast** on the
  models yet: legacy views index `Order::$status_names[$order->status]`
  with the raw string. Casting is part of v2b.6.
- Boolean casts are limited to `active`, `full_update`, `noindex`, `navbar`,
  `products` and the message consents: `isGreen`, `isPromo`, `isBestseller`
  and `isSale` hold forced values (-1, 2) written by the import and the
  legacy admin and stay integers.
- Money columns are not cast to `decimal` (the pricing helpers call
  `number_format` on them); they stay as the database returns them.
- Unknown category and CMS page slugs are now real 404s (they rendered the
  full listing or crashed), so stored redirects can answer them.
- Brands backfill: 55 rows from the anonymised production dump; empty, `0`
  and `Unbranded` mean "no brand"; slugs deduplicated (`RFX`, `RFX™`).

Deviations recorded while doing v2b.3:

- Product flags `isGreen`/`isPromo` keep the legacy four-state select (Sì,
  Sì forzato, No, No forzato) and `isSale` its 0/1/2 values: the import
  writes them and the storefront filters on them.
- Product slugs follow name and SKU as in the legacy admin unless the
  operator types a different slug; every change records a redirect.
- Variant attributes are edited as attribute/value rows and rewritten on
  save (`products_variants_attributes` has no primary key). Per-image `alt`
  is not editable yet (media alt UI belongs to v2b.4 with the SEO screens).
- Category icons stay plain files under `storage/app/public/categories/icons`
  (the storefront reads the bare file name); brand logos are stored as
  `/storage/brands/<file>` paths.
- Cache invalidation after catalogue writes is `Cache::flush()`
  (`App\Support\CatalogCache`): the storefront caches are per-entity keys
  without tags. Bulk status changes reindex the touched products explicitly.
- Saving the main variant recomputes the product's min/max price and cover
  (the legacy admin left them stale until the next import).
- "Duplicate product" was not built (no legacy equivalent, no request).

Deviations recorded while doing v2b.4:

- The three report entries became one "Report" page (orders per month with
  totals, orders by status, quotations and messages per month, top products
  by ordered pieces); the five "log" entries became the import log resource.
- The product block of a CMS page is saved by rewriting its pages_contents
  rows from the form (same rows the storefront reads); the explicit product
  picker is a searchable select instead of the legacy AJAX grid.
- Image `alt` is edited per image from the variant page (media
  custom property); the storefront gallery uses it when present.
- The sitemap is scheduled nightly at 03:30 and regenerated on demand from
  its page; nothing regenerates it on view any more.

Rough size, in resources and screens: 22 resources, 3 custom pages, 5
widgets, 3 migrations, ~8 action/enum classes, ~25 test classes. v2b.3 is
the largest step (about 40 % of the work), v2b.0 the one with the most
storefront risk.

## 6. Dependencies on the pre-public-repo tasks

The two tasks recorded after Phase 5 interact with v2b:

1. **Connector extraction** (the two suppliers → private packages). v2b.5
   must not rebuild the connector-specific admin pages in the core (raw
   data browsers, colour and category maps, markup calculators, the
   synchronous supplier fetch). Recommended order: extract the connectors
   **before** v2b.5, so the packages ship their own Filament pages
   registered through the panel (Filament plugins can register resources
   and pages from a package). If extraction slips, v2b.5 ships only the
   neutral "manage" page and the alias resource, and the legacy connector
   pages are deleted with v2b.6 without replacement.
2. **Customization abstraction** (printing → generic customizations). The
   variant resource of v2b.3 shows printing options **read-only**, exactly
   the legacy behaviour, so the model can change underneath without
   reworking the admin. Editing customizations from the admin is a feature
   of the new model, planned with it, not in v2b.

## 7. Risks and open questions

- **Filament 4 → 5 timing.** Filament 4 remains supported; the storefront on
  Livewire 3 is the constraint. Decide at v2b.7 whether the upgrade is worth
  doing before or after the first installations go live.
- **Scout and Algolia on bulk edits.** Chunked bulk actions and explicit
  reindex avoid one API call per row; must be measured on the production-sized
  catalogue (5 400 products, 35 000 variants).
- **Rich text migration.** CKEditor HTML in existing rows may contain UIkit
  classes (known for CMS pages). Filament's editor keeps unknown markup but
  cannot edit it structurally; a one-off clean-up command is likely.
- **Brands backfill.** 60+ distinct `products.brand` strings including
  `Unbranded`, `0` and empty; the migration needs a mapping review on the
  production dump.
- **Soft deletes on products.** Enabling `SoftDeletes` changes what the
  storefront queries see only if trashed rows exist; the production dump
  must be checked for rows with `deleted_at` set before enabling.
- **Province list** hard-coded in the legacy controller and in
  `Customer::PROVINCES`: keep one source (`Customer::PROVINCES`) and drop the
  other.
- **Open**: should `HomeSlide` and `Page` get the same `@mercatura-view`
  style versioning for their admin forms? (No: admin is not overridable.)
- **Open**: the Italian enum values of `customer_type` are stored in the DB
  and used by the storefront forms; renaming them is a data migration on
  every installation and is **not** proposed here.

### 7.1 v2b.7 assessment (2026-09-14)

Facts checked with composer and the upstream upgrade guides:

- The two upgrades are inseparable: Filament 4.13 requires Livewire ^3.7,
  Filament 5 requires Livewire ^4.1. Together they resolve cleanly today
  (16 package updates, no removals) now that rappasoft is gone.
- Livewire 4 breaking changes that touch this codebase: config keys
  `layout` → `component_layout` and `lazy_placeholder` →
  `component_placeholder`; `wire:model` no longer syncs child events by
  default (we only use `wire:model.live`, unaffected); endpoints move from
  `/livewire/*` to `/livewire-{hash}/*` (handled by `@livewireScriptConfig`,
  no hard-coded path in the repo); `<livewire:>` tags must self-close (we
  use `@livewire()` only). Not used here: `wire:transition` modifiers,
  `wire:scroll`, `stream()`, `$wire.$js()`, full-page components. The Vite
  bundle imports `livewire.esm.js` from vendor, which Livewire 4 still ships.
- Filament 5 ships an automated upgrade script (`filament/upgrade`); the
  guide does not enumerate the breaking changes, so the 87 panel files
  are verified by the test suite after the script runs.
- Surface: 6 storefront Livewire components, 5 views, 12 `wire:model`
  directives; 87 Filament files; 6 test classes using `Livewire::test`.

Recommendation: do it as a single, separate commit range once the panel
has been used for a while on real data, before the first installation goes
live (an upgrade after go-live needs the same work plus a maintenance
window). Storefront parity is checked as in v2b.0 (tests + Lighthouse).

## 8. What happens before v2b starts

- Approve §3 decisions (or amend them) and the dependency addition of §5
  v2b.1.
- Decide the order between connector extraction and v2b.5 (§6).
- Choose the moment: `ARCHITECTURE.md §9` places v2b after the first
  installation's go-live and the second installation.
