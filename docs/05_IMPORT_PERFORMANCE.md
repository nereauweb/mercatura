# Import performance — measurements and optimisation options

Measured on the gesca84 staging server (8 cores, 32 GB, MariaDB on the same
host, `innodb_flush_log_at_trx_commit=2`, buffer pool 12 GB) on 25-26/09/2026,
core v2.5.12, connectors sipec 1.4.1 and pfconcept 1.3.2. Catalogue: 4,218
products (2,836 PF Concept, 1,382 Sipec), 28,297 variants.

The evaluation criterion, agreed with the client: **steady-state runs on an
already formed database matter far more than the first population**, and any
optimisation must keep the logic and the resulting data identical.

## 1. What runs, and how long

| Run | Stages | Duration |
|---|---|---|
| Nightly `app:import` (Mon-Sat 05:00) | download PF + Sipec, normalise, publish, sitemap | **32 min** (PF raw 14, Sipec raw 6, normalise PF 7, Sipec 1.5, publish 4) |
| Weekly `app:import --process-customization-data=true` (Sat 21:00) | the same, plus PF print feeds stored, PF printing normalised, Sipec printing downloaded and normalised, cleanup | **6.3 h** on the first complete run (26/09) |

Breakdown of the first complete weekly run (19:18 → 01:35):

| Stage | Duration | Output |
|---|---|---|
| PF raw (products, prices, stock **+ print feeds**) | 21.8 min (+7 min for the print feeds: 296,680 print models, 30,506 feeds, 248 print prices) | |
| Sipec raw, normalise PF, normalise Sipec, publish | 5.9 + 6.7 + 1.3 + 4.1 min | 4,208 products |
| **PF printing normalisation** (`normalize:PFv3_printing`) | **330 min (5.5 h)** | 296,510 customizations on 2,718 products, ~384k areas, ~655k options, ~6.5M tiers |
| Sipec printing (download + `json_v3` normalisation) | 5 min | 28,220 customizations on 1,275 products |
| Cleanup (stale + orphans), sitemap | < 1 min (nothing to delete on a first run) | |

Resulting tables: customizations 168 MB, areas 39 MB, options 99 MB, tiers
545 MB (7,000,703 rows); database 1.3 GB. A PF product page with 124
customizations renders in 1.0 s. 118 PF products have no print data in the
supplier's feed.

Before core v2.5.12 the weekly run took 42 min because the printing flag never
reached `import:PFv3`, so no PF customization was ever produced; the 5.5 h is
the real cost of the PF print data, not a regression.

## 2. Where the 5.5 hours go

`NormalizePrintingPFv3` walks every PF variant (22,754) and, for each raw print
model of the variant (13 on average), each feed (technique × position), each
price size, colour, setup and price tier, through lazily loaded Eloquent
relations, writing row by row:

| Per | Queries today | Count | ≈ queries |
|---|---|---|---|
| tier | variant prices read (`applyMarkup` → `price_per_quantity`), insert, markup update | 6.5M | 19.5M |
| option | `firstOrCreate` (2), setup update, `tiers()->delete()` | 655k | 2.6M |
| area | `updateOrCreate` (2) | 384k | 0.8M |
| customization | lookup + upsert, `areas()->delete()` | 296k | 0.9M |
| raw reads | models per variant, feeds per model, prices per feed, sizes/colours/setups/prices per price | | 2-3M |

About 26M queries in 330 min ≈ 1,300 queries/s: the stage is bound by the
number of round trips, not by MariaDB (load average 1.5 on 8 cores, indexes on
every lookup column are in place since the 17/09 migration). The console log
adds ~7M lines (300 MB) per run.

**Steady state today is not cheaper than the first run.** Every run updates
the customization header, then `areas()->delete()` removes the areas (options
and tiers stay as orphans until `cleanup:customizations` removes them at the
end, a `DELETE … WHERE NOT EXISTS` over 7M rows) and recreates areas, options
and tiers from scratch. A second run therefore costs the 5.5 h plus the
deletes. The Sipec `json_v3` normaliser keeps areas/options with
`updateOrCreate` and rewrites only the tiers (bulk insert, markup computed in
memory), which is why 28k customizations take 5 min.

## 3. Options, impact and functional risk

Ordered by value for production. "Same data" means the rows produced are
identical to today's, which the fixture test can verify by diffing the four
customization tables before and after.

### A. Steady state: skip unchanged variants (fingerprint)

Compute per variant a hash of its raw print rows (models + feeds), of the
price trees they reference (`printCode` + feed) and of the markup rules in
force; store it with the customizations (one small `customization_sync` row
per source + variant, or a column on `customizations`). On the next run,
variants whose hash is unchanged are skipped entirely (their rows are only
"touched" in bulk so the cleanup retention keeps them); only changed variants
go through the delete-and-recreate path. A `--force` option and a monthly
forced run keep a safety net.

- Impact: weekly run from 5.5 h to **minutes** when the supplier's print data is
  stable; proportional to the change when PF updates a technique's price list
  (all variants using that `printCode` are redone).
- Risk: **low-medium**. The only failure mode is a change not covered by the
  hash; mitigated by hashing the raw rows verbatim (not selected fields), by
  including the markup rules fingerprint, and by the forced run.
- Effort: ~1 day including the fixture test.

### B. Throughput of the rewrite path (first run and changed variants)

These keep the loop and the formulas, and remove round trips. They apply to
the first run and to every variant the fingerprint marks as changed.

| # | Change | Impact on the 5.5 h | Risk |
|---|---|---|---|
| B1 | Load the variant's price tiers once per variant and compute the markup in memory (as `NormalizePrintingSipecV3::markupPercent` already does) instead of one `prices()` query per tier | −6.5M queries, ≈ −25% | none: same values from the same rows |
| B2 | Compute the tier price before insert and bulk-insert the tiers of an option in one statement (as the Sipec normaliser does) instead of `create` + `applyMarkup(save)` per tier | −13M queries, ≈ −50% | low: `applyMarkup` also handles `packaging_original_price`, which PF never sets; verified by the table diff |
| B3 | Load the 248 print price trees (5,342 rows in total) once into memory keyed by `printCode`+feed, and eager-load feeds with the models of a variant | −2-3M queries, ≈ −10% | none |
| B4 | One transaction per product | fewer commits, ≈ −5-15% (the server already avoids the fsync per commit) | low: a failure rolls back one product |
| B5 | Per-size/colour console lines only in verbose mode | ≈ −3-5%, log 300 MB → a few MB | none |

Together B1-B5 bring the first complete run from 5.5 h to an estimated
1-1.5 h, and make the changed-variant path of A proportionally cheap.

### C. Not recommended now

- **Parallel workers** by product ranges: divides the time by the number of
  workers but adds DB contention and coordination; medium risk, no need once A
  and B are in place.
- **Reducing the data** (fewer sizes/colours/tiers): changes what the
  configurator offers; out of scope by definition.

## 4. Scheduling with the current code

The weekly run started Saturday 21:00 ends around 03:20 on Sunday. It does not
overlap with the nightly import (Mon-Sat only); the 03:30 sitemap job may run
while the import is still finishing, which is harmless (both write the same
file, last one wins). Nothing to change until A/B are applied.

## 5. Recommended sequence

1. B1, B2, B3, B5 in `NormalizePrintingPFv3` (connector pfconcept), with a
   fixture test that diffs the four customization tables before and after:
   same rows, ~4× faster. B4 optional.
2. A (fingerprint skip) in the connector framework (`ConnectorCommand` helper
   usable by both connectors), with the forced-run option and a log line
   "variants skipped / rewritten".
3. One weekly run on staging to measure both the forced (first-run) time and
   the steady-state time.
