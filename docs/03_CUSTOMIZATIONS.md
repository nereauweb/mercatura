# v2c — Customizations (printing → generic product customization)

Status: **approved 2026-09-15 (decisions §3 as revised on real data). v2c.0 done (2026-09-15); v2c.1 next.**
Written after the connector extraction and the repository split, from a
full read of the printing domain in the core, the demo seeders and the two
supplier packages. Decisions marked *(proposed)* need approval before v2c
starts. It complements `ARCHITECTURE.md` (§13 makes `printing_variants*`
part of the normalized layer that connectors write) and
`02_V2B_ADMIN.md` §6.2 (the variant resource shows printing read-only so
that the model can change underneath).

## 1. Scope

Today "customization" means one thing: a print on a product, described by
a technique, a position, a size and a number of colours, priced per
quantity tier plus a setup ("impianto") and a start ("avviamento") cost.
Embroidery, laser engraving, sublimation, labels and tags already exist in
the data as *techniques of a print*, because the model has no other place
for them. The task recorded before the public repo (`pre-public-repo-todo`
task 2) is to turn this into a **customization** domain that:

- holds decorations that are not prints (embroidery, engraving, labels,
  digital transfer, none) without forcing a taxonomy the feeds do not carry;
- stays the contract that supplier connectors write, with no supplier
  assumption left in the core;
- prices every technique through one code path, shared by the configurator,
  the cart, the order and the demo seeder;
- snapshots what the customer bought, so an order does not depend on the
  supplier's price table of the day;
- can later be edited from the admin (a feature of the new model, last
  phase, not required for parity).

Out of scope: a new configurator UX, new pricing rules, per-installation
pricing policies, a designer/upload tool. Parity first (CLAUDE.md rule 5):
every phase below keeps totals, pages and payloads identical unless the
deviation is listed in §3.

## 2. What the analysis found

### 2.1 Size and shape

- Four tables in a strict tree with `ON DELETE CASCADE` foreign keys:
  `printing_variants` (1 row per variant × technique × position) →
  `printing_variants_sizes` → `printing_variants_colors` →
  `printing_variants_prices` (quantity tiers). One `order_item_printings`
  table, one `quotations_items.printing` yes/no string, and denormalized
  "default print" columns on `products`, `normalized_products` and
  `normalized_products_variants`.
- Four models under `App\Models\ImportData\VariantPrinting*`, plus
  `OrderItemPrinting`, plus helpers on `Product` and `ProductVariant`
  (`printings()`, `default_printing()`, `min_print_quantity()`,
  `price_per_quantity(..., $with_default_printing)`).
- Pricing lives in **two copies of the same algorithm**:
  `FrontendProductController::build_articles_request` (configurator
  summary and PDF) and `FrontendCartController::session_data_to_cart`
  (cart, checkout, order). The demo seeder has a third, partial copy.
- Storefront surface: `frontend/components/product/configurator.blade.php`,
  `resources/js/storefront/product-configurator.js`, seven POST endpoints
  under `/prodotti/configuratore` and `/prodotti/personalizzazione`, the
  cart item component, the order page (print file upload), the quotation
  form (yes/no radio), the mail partials and the configurator PDF.
- Admin surface (v2b): variant edit shows printings **read-only**
  (technique, position, minimum, default); order infolist lists
  `printing_label` per item; quotation infolist shows the yes/no string;
  the Imports page dispatches the printings job. No admin editing.
- Connectors: both packages write the four tables directly with their own
  commands (`normalize:SipecV2_printing`, `normalize:SipecV3_printing`,
  `normalize:PFv3_printing`, `update:app-printings-pf`, and the PF price
  and setup calculators). Sipec keeps two pipelines side by side
  (`excel`, `json_v3`) and the live one is chosen by `printingPipelines()`
  through `App\Support\Connectors\PrintingPipeline`.
- Tests: the pipeline filter (ConnectorFrameworkTest), three 404 checks on
  the endpoints (ProductPageTest), two mail bodies, the demo product page
  showing a technique. **No test covers pricing, the cart line, the
  cart-to-order copy or the sibling lookup.**

### 2.2 Surface inventory

| Area | Where | What it hardcodes about "printing" |
|---|---|---|
| Schema | `printing_variants*`, `order_item_printings`, `quotations_items.printing`, `products.default_print_*`, `normalized_*.default_print_*` / `printing_default_*` | table and column names; colours as the only pricing dimension; size as width × height |
| Models | `ImportData\VariantPrinting`, `VariantPrintingSize`, `VariantPrintingColor`, `VariantPrintingPrice`, `OrderItemPrinting` | Italian labels built in PHP (`label()`, `printing_label()`, `setup_label()`, `start_label()`); `sibling()` matches by technique + position strings |
| Pricing | `FrontendProductController:211-374`, `FrontendCartController:491-652`, `VariantPrintingColor::calculate_print_price`, `VariantPrintingPrice::calculate_markup_price`, `ProductVariant::price_per_quantity` | duplicated algebra; VAT 0.22, delivery 16, free shipping 500, under-minimum surcharge 40 as literals; markup band of the *article* reused for the print |
| Storefront | configurator view + JS, `ProductPageData::configurator()`, `price-table`, product page "recommended technique / print area" | positions × techniques × sizes × colours cascade; `printings: [color ids]` as the only transport |
| Cart / order | session cart, `store_order`, `order_item_printings`, `FrontendOrderController::upload_printing_image` | order stores only the option id and a label; setup, start, packaging and surcharge are folded into `order_item.price` |
| Quotation | `quotations_items.printing`, quotation form radio, `FrontendQuotationController::store` | `'Sì'`/`'No'` literals in markup and controller |
| Mail / PDF | `Order::mail_export_items` (`'Personalizzazioni: '`), `mail/partials/{order,quotation-items}`, `pdf/configurator_summary` | pre-joined strings |
| Admin | `ProductVariantForm` printings section, `OrderInfolist`, `QuotationInfolist`, `Imports` page | read-only; labels `admin.catalog.printings`, `admin.order.printings` |
| Connectors | `ImportConnector::STAGE_PRINTINGS`, `printingPipelines()`, `PrintingPipeline`, `ImportPrintingsJob`, `cleanup:printing_variants` | the stage name and the pipeline concept |
| Demo | `Demo/Catalog.php` (positions, techniques, tiers), `DemoCatalogSeeder::seedPrintings`, `DemoCustomersSeeder` (order printing, quotation `'Sì'`) | embroidery and engraving seeded as print techniques |
| Lang | `frontend.product.configurator.*`, `frontend.product.{print_area,price_printed,...}`, `frontend.cart.{printing,print_files_*}`, `frontend.quotation.printing*`, `admin.*.printing(s)`, `mail.labels.printing` | key names say "print"; they are API for skins (ARCHITECTURE §2) |

### 2.3 Data model facts that shape the rewrite

- The tree is the right shape. Every customization is "a
  decoration at a place on the article, with a size or extent, with an
  option that drives the price (colours, threads, passes), priced by
  quantity tiers plus fixed costs". What changes per technique is the *meaning*
  of the middle levels, not the number of levels.
- The **option level is the pricing dimension**, not a colour: PF and
  Sipec use `number_of_colors` = `0` for full colour, `N` for N colours;
  embroidery would use stitch classes, engraving one option. `setup`,
  `original_setup`, `setup_multiplier`, `start_cost` live there.
- Prices are **baked at import**: `printing_variants_prices.price` is
  `original_price` marked up with the *article's* band
  (`MarkupRules::percent(qty × cost)` on `product_markups`), by the PF
  calculator or inline by Sipec V3. The storefront then reads `price`
  or, when it has the article markup at hand, recomputes from
  `original_price`. Both paths must survive.
- The tier is chosen by the **whole cart line quantity** while the
  extended price multiplies the **single article quantity**
  (`calculate_print_price($total_quantity, $quantity)`). This is a
  feature (one print run across sizes and colours), and the reason the
  cart re-resolves the equivalent option on every article with
  `sibling()`.
- Packaging is a flag on the customization row (`has_packaging`) with its
  own tier prices (`packaging_price`), priced only when the line asks for
  it.
- `pipeline` is a connector concern (Sipec keeps `excel` and `json_v3`
  side by side); the core only filters by it. It stays.
- `order_item_extras` (`label`, `price`) exists in the schema and is
  written by nobody: the natural home for setup, start, packaging and
  surcharge lines.
- The two dead columns families (`price_method_1/2`,
  `packaging_price_method_1/2`) are in no `$fillable` and no query.
- `printing_variants.deleted_at` exists but the model has no
  `SoftDeletes`; `cleanup:printing_variants` hard-deletes.

### 2.4 Defects found (fixed in v2c.0, before anything moves)

1. `App\Models\ImportData\NormalizedProduct::printing_positions()` and
   `NormalizedProductPrintingPosition` reference a class and a pivot
   table that do not exist; `Product::default_printing_position()` calls
   itself (infinite recursion); `Product::printing_techniques_positions()`
   and `default_printing_position_technique_label()` depend on the
   commented-out relation. Dead since the monolith.
2. `OrderItemPrinting::printing()` resolves `printing_variant_color_id`
   against `VariantPrinting` instead of `VariantPrintingColor`.
3. `QuotationItem::quotation()` belongs to `Order`.
4. `printing_variants.variant_id_index` indexes `id`, not `variant_id`.
5. `VariantPrinting::price_per_quantity()` returns the smallest matching
   tier (loop without `break`); unused, as is `default_setup_per_unit()`.
6. `/prodotti/personalizzazione/setup` calls `calculate_print_price()`
   with one argument (fatal on a valid id); `/prodotti/personalizzazione/dimensioni`
   is never called by the JS. Both are dead endpoints.
7. Cart and configurator disagree on `setup_multiplier = 0`: the
   configurator treats it as 1, the cart multiplies by 0. Same payload,
   different total.
8. `connectors/pfconcept` `update:app-printings-pf` writes
   `printing_variants.last_seen_in_feed`, a column that does not exist.
9. `ProductMarkup` / `product_markups` (seeded by `CoreSeeder`) is read
   by nobody; the live bands (renamed `product_markups` on 2026-09-16) are
   edited in the admin under Sistema → Regole di prezzo.
10. `order_items` in the schema dump has no `unit_price` column while
    `store_order` writes one (to verify against the migrated fixture).

### 2.5 Evidence from real supplier data (fixture database, 2026-09-15)

The local fixture holds the output of real imports: 310 588 customization
rows (PF 261 436; Sipec 19 639 `excel` + 29 513 `json_v3`), 393 039 areas,
679 520 options, 6 657 897 tiers. The raw feed tables are empty locally and
no supplier credential is on this machine, so the feeds themselves were not
re-downloaded; the normalized rows are what the connectors produced from
them.

- **Techniques are free labels, 31 for PF and 10 + 14 for Sipec**, mixed
  Italian and English ("Serigrafia", "Embroidery fixed", "Ricamo 3D",
  "Incisione Laser", "Etichetta resinata", "Nello stampo - drinkware",
  "Logo Light-Up", "Digital Sticker", "Dtf", "Digitale (uv)", "Laser",
  "Stampa a caldo", "Etichetta adesiva"…). Sipec `json_v3` also carries a
  technique code whose prefix groups them (`TS*`/`TX*`/`S*` screen,
  `T*` pad, `L*` laser, `EC*` embroidery, `SC*` hot stamping, `DF*` DTF,
  `DV*` digital UV, `SU*` sublimation, `DT*` label, `DR*`/`DP*` digital);
  PF's feed has an `impMethodCode` per method whose value list is not in
  the fixture. **Neither feed exposes a decoration family field.**
- **Every non-ink technique already fits the four-level tree** without
  special cases: embroidery is priced by option labels "Fino a 12",
  "Fino a 5", "1" (PF) or "1".."5" thread colours (Sipec, five options
  with setup 20 → 80 €); laser has one option labelled "1", "Incisione"
  or "Engraving"; hot stamping "Hot stamping" / "Impressione a caldo";
  labels and in-mould "full color". Areas are rectangles with real
  millimetres (903 PF circles, 3 rows without width).
- **`number_of_colors` is already not a colour count**: PF stores 1 for
  "Fino a 12" and "full color", Sipec stores 1 for "Engraving". It is the
  multiplier the pricing uses, nothing more.
- Packaging exists only on Sipec (7 949 + 13 897 rows with packaging
  tiers); PF has none. Start costs exist only on Sipec `excel`.
  `max_print_position` is set only by Sipec `json_v3` (always 2).
  Minimum quantities: PF 0, Sipec 50–250.

Conclusion: the *structure* is generic today and needs no new level; a
*family* of the decoration is not in the data and would be a mapping we
maintain by hand. That is the reason for decision 2 below.

### 2.6 What is already generic

The pipeline filter, the connector stage mechanism, the import jobs, the
cleanup command, the media handling of print files, the configurator's
cascade (place → technique → extent → option) and the tier lookup are all
technique-agnostic once names change. The abstraction is mostly a rename plus
a discriminator plus one pricing service, not a redesign.

## 3. Decisions *(proposed)*

1. **Name.** The domain is *customizations*: `Customization` (one
   decoration offer on a variant), `CustomizationArea` (was size: the
   extent of the decoration), `CustomizationOption` (was colour: the
   priced option), `CustomizationTier` (was price: quantity tier). Tables
   `customizations`, `customization_areas`, `customization_options`,
   `customization_tiers`; order snapshot `order_item_customizations`.
   Namespace `App\Models\Customizations\`. The legacy classes stay as
   deprecated thin subclasses for one minor release so the connector
   packages keep working until they are updated (v2c.5).
2. **No kind enum; an optional, untyped family.** The taxonomy first
   proposed (print / embroidery / engraving / label / digital) was a guess
   made before looking at the data (§2.5): the feeds carry no family and
   every technique already prices through the same tree. v2c therefore
   adds only `customizations.family` VARCHAR(32) NULL, **not an enum,
   nothing depends on it**: connectors fill it when they know it (Sipec
   `json_v3` can derive it from the technique code prefix; PF from
   `impMethodCode` once its value list is verified), the admin can set it
   (v2c.6), the core uses it for a badge and a catalogue filter only when
   present. `technique_label` stays the free label the supplier gives and
   remains the key of every lookup. A typed enum, if ever, is a later
   decision taken on verified feed data.
3. **Structure renamed, columns kept.** Tables and classes get the generic
   names of decision 1 because the data proves the structure is generic;
   column names stay as they are (`number_of_colors`, `max_colors`,
   `max_print_position`, `type`, `width_mm`, `height_mm`) because their
   semantics are the pricing multiplier and extent the connectors write
   today, and renaming them would only move the same guess into the
   schema. `customizations.attributes` and `customization_areas.attributes`
   (JSON, nullable) are added for data a supplier gives beyond the shared
   columns (PF `laserColor`, `allowedColorType`, `maxAreaCm2`; Sipec
   `accetta_pantone`), written by connectors, read by nobody in the core
   until a feature needs them. Only the four dead `*_method_*` columns
   are dropped.
4. **One pricing service.** `App\Support\Customizations\LinePricer`
   computes a configured line (articles + option ids + packaging) into
   the same structure both controllers build today: per-article price,
   per-customization price, packaging, start, setup, under-minimum
   surcharge, totals, VAT, unit prices. Both controllers and the demo
   seeder call it. The formulas of §4.3 are transcribed verbatim from the
   current code; the only deviation is defect 7, resolved in favour of the
   configurator (`setup_multiplier` 0 counts as 1) because that is the
   number the customer saw before adding to the cart.
5. **Magic numbers into config.** VAT rate, delivery cost, free-shipping
   threshold and under-minimum surcharge move to `config/mercatura.php`
   (`pricing.vat_rate`, `pricing.delivery_cost`,
   `pricing.free_delivery_from`, `pricing.under_minimum_surcharge`) with
   today's values as defaults. Installations set them in `.env`. Not a
   behaviour change.
6. **Order snapshot.** `order_item_customizations` stores, per article
   line: option id, family, technique, position, area label, option label,
   units, unit price, quantity, packaging unit price, plus `label` (the
   rendered string, for mails and legacy reads) and `file` (was
   `print_file`). Setup, start, packaging and surcharge become
   `order_item_extras` rows with a `type` column. Order totals are
   unchanged; they become auditable. The existing
   `order_item_printings` rows are migrated (label copied, other columns
   backfilled from the live option where it still exists, null otherwise).
7. **Cart transport unchanged.** The session cart keeps
   `{articles, printings: [option ids], has_packaging}`; the key
   `printings` is renamed `customizations` with a read fallback for
   sessions alive during the deploy. The configurator JSON request and
   response shapes do not change; the response gains `family` per line.
8. **Endpoints.** The four `/prodotti/personalizzazione/*` URLs and route
   names stay (skins may link them). The two dead ones
   (`…/dimensioni`, `…/setup`) are removed — **route removal, needs the
   explicit approval CLAUDE.md asks for**.
9. **Labels out of PHP.** `label()`, `printing_label()`, `setup_label()`,
   `start_label()`, `'Personalizzazioni: '`, `'Confezionamento'`,
   `'Avviamento'`, `'Sotto soglia minima'`, `'Sì'`/`'No'` become lang
   keys under `frontend.customization.*` and `mail.labels.*`, rendered by
   one presenter (`CustomizationLabel`). Output strings identical.
10. **Lang and view names are not renamed.** Existing keys
    (`frontend.product.configurator.*`, `frontend.cart.printing`, …) and
    view names stay; new keys are added next to them. A skin that
    overrides the configurator today keeps working. Renames, if ever, are
    a separate documented decision (ARCHITECTURE §2).
11. **Quotation.** `quotations_items.printing` becomes
    `customization` (varchar 255): the yes/no radio stays, the stored
    value is the lang string as today, and the column can later carry a
    free description. Mail partial and admin infolist read the new column.
12. **Connector contract.** `ImportConnector::STAGE_PRINTINGS` →
    `STAGE_CUSTOMIZATIONS` (old constant kept as alias for one minor),
    `printingPipelines()` → `customizationPipelines()`,
    `App\Support\Connectors\PrintingPipeline` →
    `CustomizationPipeline`, `ImportPrintingsJob` →
    `ImportCustomizationsJob` (old class kept as subclass for queued
    payloads), `cleanup:printing_variants` → `cleanup:customizations`
    (old signature kept as alias). Connectors write `family` only when
    the feed makes it certain, null otherwise. The normalized layer definition in
    ARCHITECTURE §13 is updated to the new names.
13. **Admin editing is the last phase and optional for parity.** A
    relation manager on the variant (customizations → areas → options →
    tiers) with the same gating (`catalog.manage`), plus a family badge
    on the read-only section from v2c.2 onwards. Import-owned rows show
    their source and are editable at the installation's risk (an import
    overwrites them), exactly like prices today.
14. **Soft deletes stay off** on the customization tables; the
    `deleted_at` columns are dropped with the rename (nobody writes them)
    and the cleanup command keeps hard-deleting. Foreign keys keep
    cascading.

## 4. Target design

### 4.1 Schema (one migration per phase, all in the core)

```
customizations                (was printing_variants)
  id, source, pipeline, source_product_sku, source_variant_sku,
  product_id, variant_id, normalized_product_id, normalized_variant_id,
  family VARCHAR(32) NULL,
  technique_label, technique_main_code, position_label, position_code,
  image, is_default, processing_days, has_packaging, packaging_code,
  minimum_quantity, max_colors, max_print_position,
  attributes JSON NULL, timestamps
  UNIQUE (source, pipeline, source_product_sku, source_variant_sku, technique_label, position_label)
  INDEX (variant_id) — fixes defect 4; INDEX (product_id), (family), (source, pipeline)

customization_areas           (was printing_variants_sizes)
  id, customization_id, label, type, width_mm, height_mm,
  attributes JSON NULL, timestamps

customization_options         (was printing_variants_colors)
  id, area_id, label, number_of_colors, setup_multiplier,
  setup, original_setup, start_cost, original_start_cost, timestamps

customization_tiers           (was printing_variants_prices)
  id, option_id, from_quantity, price, original_price,
  packaging_price, packaging_original_price, timestamps
  (the four *_method_* columns dropped)

order_item_customizations     (was order_item_printings)
  id, item_id, option_id NULL, family NULL, technique_label, position_label,
  area_label, option_label, number_of_colors, quantity, unit_price,
  packaging_unit_price NULL, label, file, timestamps
  INDEX (item_id)

order_item_extras             (exists)
  + type VARCHAR(16) NOT NULL DEFAULT 'other'   (setup|start|packaging|surcharge|other)
  + customization_id NULL (the order_item_customizations row it belongs to)

quotations_items.customization VARCHAR(255) (was printing VARCHAR(32))
products.default_customization_technique / _position (was default_print_*)
normalized_products.default_customization_* (was default_print_*)
normalized_products_variants.customization_default_* (was printing_default_*)
```

Migration strategy: `RENAME TABLE` (instant in MariaDB), `ALTER` to add
`family` and `attributes`, drop the four dead columns, recreate
the foreign keys with the new names. Runs in minutes on the largest known
installation (tens of thousands of customizations, hundreds of thousands
of tiers). The `2026_09_10_121000` pipeline migration and the widen
migrations stay as they are (they run before the rename on a monolith
database).

### 4.2 Models and support classes

- `App\Models\Customizations\{Customization, CustomizationArea, CustomizationOption, CustomizationTier}`
  with the relations of today (`areas()`, `options()`, `tiers()`,
  `customization()`, `area()`, `option()`), `sibling($variantId)` moved
  to `Customization::equivalentOptionFor(CustomizationOption, int $variantId)`
  (same matching: technique + position + pipeline, then area label, then
  option label).
- `App\Models\OrderItemCustomization`, `App\Models\OrderItemExtra` (type
  aware).
- `App\Support\Customizations\LinePricer` (§4.3), `CustomizationLabel`
  (presenter: `option($option)`, `line($option)`, `setup($option)`,
  `start()`, all through lang keys), `CustomizationPipeline` (renamed
  filter, same three static methods).
- `Product` and `ProductVariant`: `customizations()`,
  `defaultCustomization()`, `minCustomizationQuantity()`; the old method
  names stay as deprecated forwards until v2c.5 (they are used by views
  a skin may have copied).
- Deprecated aliases (one minor): `App\Models\ImportData\VariantPrinting`
  `extends Customization`, and so on for the four classes,
  `OrderItemPrinting extends OrderItemCustomization`. Removed in v2c.5.

### 4.3 Pricing service (formulas transcribed from today's controllers)

Input: `articles: [[variant_id, quantity]]`, `options: [option_id]`,
`packaging: bool`. Output: the line structure both controllers produce
today (`lines[]` with style/column_1..3, `total_price`, `unit_price`,
`total_vat`, `total_quantity`, `total_additional_costs`,
`total_taxed_price`, `unit_taxed_price`, `minimum`,
`processing_days`) plus, new, a typed `extras[]` list (setup, start,
packaging, surcharge) and the option snapshot per article (used by
`store_order`).

```
total_quantity = Σ quantity
per article:
  original      = variant->price_per_quantity(total_quantity, true)
  markup%       = variant->get_markup_percent(total_quantity, original)   // MarkupRules band
  unit          = original + round(original * markup% / 100, 2)
  line price    = quantity * unit
  additional    = quantity * variant->additional_unit_costs_per_quantity(quantity)   // SIAE, VAT-free
  per option (only if CustomizationPipeline::optionIsLive):
    opt         = equivalent option for this variant (sibling)
    tier        = highest from_quantity <= total_quantity
    unit_print  = round(tier.original_price * (1 + markup% / 100), 2)
    price       = quantity * unit_print
    packaging   = packaging ? quantity * tier.packaging_price : 0
    minimum     = max(minimum, customization.minimum_quantity)
    days        = max(days, customization.processing_days)
per option, once per line:
  start         = option.start_cost (if > 0)
  setup         = option.setup * (option.setup_multiplier ?: 1)          // decision 4
if total_quantity < minimum: surcharge = config pricing.under_minimum_surcharge (40)
total_price   = Σ line prices + Σ option prices + Σ packaging + Σ start + Σ setup + surcharge
total_vat     = round(total_price * pricing.vat_rate, 2)                  // 0.22
unit_price    = (total_price + additional) / total_quantity
```

Characterisation tests (v2c.0) pin these numbers on the demo catalogue
before the service exists, then the service must reproduce them to the
cent.

### 4.4 Storefront

- `ProductPageData::configurator()` returns the same payload; each
  technique gains `family` (null when unknown); the product page "recommended
  technique / print area" block reads `defaultCustomization()`.
- The JS keeps its cascade and request shape; only the request key
  `printings` → `customizations` (server accepts both for one release).
- The configurator view keeps its name and stacks; copy gains
  technique-aware wording only where a new key is added (e.g. "Ricamo" is no
  longer introduced as a print technique).
- Endpoints: `image_and_sizes` → returns `areas`; `colors` → returns
  `options` (`id`, `label`, `units`); shapes otherwise unchanged. Dead
  endpoints removed (decision 8).

### 4.5 Cart, checkout, order

- `session_data_to_cart` calls `LinePricer`; the cart item view renders
  `CustomizationLabel::line()` for each option and one row per extra
  (today: packaging row only; setup and start were invisible in the cart
  and remain folded in the line price — deviation recorded: **no**, the
  cart shows the same rows as today; extras are stored, not displayed,
  until an explicit UX decision).
- `store_order` writes `order_item_customizations` (snapshot) and
  `order_item_extras` (typed) from the pricer output. `order_item.price`
  and order totals unchanged.
- `upload_printing_image` → `uploadCustomizationFile`, same route name
  and URL, writes `order_item_customizations.file`.
- Mails and PDF read the snapshot labels; output strings identical.

### 4.6 Quotation

Column rename, lang keys for yes/no, controller reads
`customization`. The form's radio keeps its markup and `name`.

### 4.7 Connector contract (ARCHITECTURE §13 update)

- Stage `customizations` (alias `printings`), `customizationPipelines()`,
  `CustomizationPipeline`, jobs and commands renamed with aliases.
- Connectors write the renamed tables through the core models or raw
  inserts (`customization_tiers`); `family` is optional and written only
  from a verified feed field (Sipec code prefix; PF `impMethodCode` after
  its values are checked on a real download). Sipec and PF
  Concept packages get one release each (`v1.1.0`, requires core
  `^2.2`), Silan's skeleton is untouched.
- `cleanup:customizations` keeps the behaviour (live pipelines only,
  orphans) and gains chunking (the current `->get()` loads every stale
  row in memory).

### 4.8 Admin (v2c.6)

- Variant edit: the read-only section becomes a relation manager
  `CustomizationsRelationManager` (table: family badge when set, technique,
  position, default, minimum, source) with nested edit of areas, options
  and tiers in a modal form; `SetDefaultCustomization` action in
  `app/Actions/Catalog`.
- Order infolist: per article the snapshot rows and the typed extras.
- Quotation infolist: the `customization` column.
- Copy in `lang/it/admin.php` (`admin.catalog.customizations.*`), gated
  by `catalog.manage`.

### 4.9 Tests

- v2c.0 characterisation: `LinePricingTest` (configurator summary and
  cart totals on demo data: plain, printed, packaged, under minimum,
  multi-colour, two positions, setup_multiplier 0), `CartToOrderTest`
  (session cart → order rows and totals), `SiblingLookupTest`,
  `ConfiguratorEndpointsTest` (shapes, not only 404s).
- Each phase keeps those green; v2c.2 adds schema tests
  (`CustomizationSchemaTest`: tables, columns, foreign keys, migrated
  order rows), v2c.3 adds snapshot assertions, v2c.6 admin tests.
- Connector package tests updated with their release.

## 5. Phases and stop criteria

**v2c.0 — Baseline and defects.** Characterisation tests of §4.9 against
the current code; fix defects 1–6 and 8–9 of §2.4 (dead code removed,
relations corrected, index fixed, dead endpoints removed on approval,
PF write of a nonexistent column removed in the package); defect 7 is
*documented*, not fixed (parity). Verify defect 10 against the fixture.
Stop: tests green with and without connector packages; no behaviour
change except the removed dead endpoints; `product_markups` decision
recorded (drop table or keep as documented no-op).

**v2c.1 — Pricing service.** `LinePricer` extracted; both controllers and
the demo seeder use it; magic numbers in config with today's defaults;
defect 7 resolved (decision 4) and the characterisation test updated for
that one case.
Stop: totals identical to v2c.0 on every characterisation case except
the documented one; no duplicated formula left in `app/Http`.

**v2c.2 — Schema and models.** Migration of §4.1 (rename, `family`,
`attributes`, dead columns dropped, indexes), new models, deprecated
aliases, `CustomizationPipeline`, presenter with lang keys,
`Product`/`ProductVariant` forwards; the demo seeder leaves `family`
null except where the technique name is unambiguous in the demo itself.
Connector packages keep working through the aliases (verified with both
checked out) and against the migrated fixture (310 k rows).
Stop: fresh `migrate --seed` and the migrated fixture both pass the
whole suite; schema dump regenerated; no supplier or print assumption in
`app/Models/Customizations`.

**v2c.3 — Storefront, cart, order snapshot.** Configurator payload and
endpoints on the new models; cart key rename with fallback;
`order_item_customizations` + typed extras written by `store_order`;
existing order rows migrated; upload, mails, PDF on the snapshot.
Stop: characterisation tests green; a demo order placed before the
migration renders identically after it; `CartToOrderTest` asserts the
snapshot and extras.

**v2c.4 — Quotation and admin read-only.** Column rename, lang keys,
admin infolists on the new names, family badge in the variant section,
Imports page action renamed with alias.
Stop: quotation flow and mails identical; admin tests green.

**v2c.5 — Connector contract and packages.** Contract renames with
aliases, ARCHITECTURE §13 rewritten, `cleanup:customizations` chunked;
Sipec and PF Concept packages updated, tested against the core, tagged
`v1.1.0`; aliases removed from the core; core tagged `v2.2.0`.
Stop: core suite green without packages; package suites green with the
new core; `git grep -i printing` in `app/` returns only the deprecated
forwards listed in this document (none after this phase) and the lang
keys of decision 10.

**v2c.6 — Admin editing (optional, separate approval).** §4.8.
Stop: a customization can be created, edited and set default from the
variant page; an import re-run overwrites it and the log says so.

Deviations from this plan are recorded per phase in §8 as they happen,
as in `02_V2B_ADMIN.md`.

## 6. Dependencies and ordering

- v2c.0 and v2c.1 touch no schema and can ship any time; they also
  reduce the risk of every later phase (money paths under test first).
- v2c.2 needs the connector packages checked out locally to verify the
  aliases; it does not need a package release.
- v2c.5 is where the packages must be released together with the core
  minor; the installation `mercatura-gesca84` updates `composer.lock`
  in the same deploy.
- The public release of the core can happen after v2c.5 (no print-only
  assumption left) or, if urgency requires, after v2c.2 (the schema is
  neutral, the code still carries the aliases). Recommended: after v2c.5.
- The gesca84 graphics phase is independent and stays last.

## 7. Risks and open questions

- **Money.** The only real risk: a cent of difference in a cart total.
  Mitigated by the characterisation tests written *before* any change
  and by keeping the tier/markup algebra untouched.
- **Renaming with data.** `RENAME TABLE` is atomic; the column renames
  and the added columns are one transaction per table on MariaDB
  10.11; downtime of minutes, not hours. The migrated fixture is the
  rehearsal.
- **Sessions in flight** during the v2c.3 deploy hold `printings` keys;
  the read fallback covers them for one release.
- **Connector pace.** Between v2c.2 and v2c.5 the packages run on
  aliases; nothing forces them to update early, but their tests will
  print deprecation notices.
- **Family values.** Deliberately untyped (decision 2). Before v2c.5 the
  two feeds should be downloaded once with real credentials (not on this
  machine) to list PF `impMethodCode` values and confirm the Sipec code
  prefixes; until then connectors write `family` only where the mapping is
  certain, otherwise null.
- **Open:** should `has_packaging` become a customization with family
  `packaging` instead of a flag with its own tier columns? Not in v2c
  (parity); worth a decision when a second supplier prices packaging
  differently.
- **Open:** `pricing.vat_rate` as a single rate is what exists today;
  per-item VAT is out of scope.

## 8. Deviations recorded during execution

**v2c.0 (2026-09-15).** Characterisation tests in `tests/Feature/Customizations/`
(`LinePricingTest`, `SiblingLookupTest`, `CartToOrderTest`,
`ConfiguratorEndpointsTest`) on `Tests\Support\CustomizationFixture`; the
hand-computed totals matched the current code at the first run. Defects
1–6 and 8–9 fixed; 7 pinned by a test as the documented asymmetry; 10
confirmed on the migrated fixture (`order_items.unit_price` never existed,
the write was silently dropped by mass assignment) and resolved by adding
the column and storing the value — the only data the phase adds.
`product_markups` dropped (decision: nothing read it). The two dead
routes removed. PF Concept package: the write of the nonexistent
`last_seen_in_feed` column removed (`v1.0.1`). No behaviour change in any
total or page.

## 9. What needs approval before v2c starts

- Decisions 1–14 of §3, in particular: the names (1), no kind enum and
  an optional untyped family (2), columns kept (3), the cart/configurator asymmetry resolved in favour of the configurator
  (4), the typed extras on orders (6), the quotation column rename (11),
  the connector contract renames with one-release aliases (12).
- The removal of the two dead routes (decision 8, CLAUDE.md "ask before
  changing a route").
- Whether v2c.6 (admin editing) is part of v2c or a later phase.
- Whether the public release waits for v2c.5 (recommended).
