# v2d — Storefront flows: modal configurator, onepage checkout, quick quote, samples, delivery date

Status: **approved 2026-09-16 (decisions of the owner in §2). F1–F4 done (2026-09-17); F5 in progress.**
Written for the gesca84 installation (`mercatura-gesca84/docs/GESCA84_SKIN.md`
has the page-by-page comparison with gesca84.it) but every feature here is a
**core feature behind configuration**, as ARCHITECTURE §1 requires: an
installation chooses a flow in its `.env`, the skin styles it. The current
flows stay available and remain the core defaults.

## 1. Scope

gesca84.it (Magento 1) differs from the core storefront in five flows and a
handful of catalogue behaviours:

1. **Configurator**: a modal with a five-step accordion (colours →
   quantities → decoration question → printing → artwork → summary with
   shipping date) instead of the inline four-step panel.
2. **Checkout**: the Magento "onepage" accordion (checkout method, billing,
   shipping, shipping method, payment, review) with a progress column,
   instead of four pages.
3. **Quick quote**: a modal reachable from every page (contact fields + the
   list of products to quote), instead of the quotation page.
4. **Sample request**: one piece of a colour/size at the unit price, with
   its own totals and shipping date, added to the cart.
5. **Shipping date** shown in the summary and the cart.

Plus: lazy-loaded product tabs, hover image on cards, quote-only products,
and a performance pass. Filters stay the core's (owner's decision).

## 2. Decisions (owner, 2026-09-16)

- The configurator **reproduces the gesca84 modal flow**, with one
  functional difference: **different techniques may be chosen for different
  positions** (gesca84 allows several positions with one technique only),
  keeping the same interface.
- Product information tabs **load their content lazily**; the whole
  storefront is optimised for performance, where the old site is poor.
- **All** of shipping date, sample request and quick-quote modal are wanted.
- Search placeholder: "Cerca un gadget tra più di 4000 articoli".
- **Filters stay the core's** (promo84's), not gesca84's.
- The **checkout follows gesca84.it**, i.e. the Magento 1 onepage flow, as
  closely as possible.

Assumptions taken (say if wrong): no anonymous guest checkout — the first
onepage step is "Accedi / Registrati" and registration creates the account
inline (the core already requires an account, and a B2B invoice needs the
fiscal data anyway); one shipping method ("Corriere espresso", cost from
`mercatura.pricing`); packaging, which gesca84 does not offer and the core
does, stays available inside the printing step when the supplier has it.

## 3. Configuration

`config/mercatura.php`, new block:

```php
'storefront' => [
    'configurator' => env('MERCATURA_CONFIGURATOR', 'panel'),   // panel | modal
    'checkout'     => env('MERCATURA_CHECKOUT', 'steps'),       // steps | onepage
    'quick_quote'  => env('MERCATURA_QUICK_QUOTE', 'page'),     // page | modal
    'samples'      => (bool) env('MERCATURA_SAMPLES', false),
    'shipping_date'=> (bool) env('MERCATURA_SHIPPING_DATE', false),
    'artwork_in_configurator' => (bool) env('MERCATURA_ARTWORK_IN_CONFIGURATOR', false),
    'card_hover_image' => (bool) env('MERCATURA_CARD_HOVER_IMAGE', false),
],
'delivery' => [
    'cutoff_hour' => 12,          // orders after this hour count from the next working day
    'holidays'    => [],          // 'MM-DD' or 'YYYY-MM-DD'
],
```

gesca84 sets `modal`, `onepage`, `modal`, and every flag on. The core demo
keeps the defaults so the existing tests and the demo instance do not move.

## 4. Design

### 4.1 Data the modal needs in one request

Today the panel cascades three requests per position (technique → area →
options). The modal needs everything at once, priced for the quantity the
customer just entered. New endpoint (POST, JSON):

`/prodotti/configuratore/opzioni` `{ article_id, articles: [[variant, qty]…] }` →

```json
{ "quantity": 250, "positions": [
    { "id": 12, "label": "Fronte", "image": "…", "techniques": [
        { "id": 12, "label": "Serigrafia", "family": null, "areas": [
            { "id": 40, "label": "10x10 cm", "options": [
                { "id": 91, "label": "1 colore", "unit_price": 0.42, "setup": 30.0, "start_cost": 0, "minimum_quantity": 50 } ] } ] } ] } ],
  "packaging": { "available": true, "unit_price": 0.65 } }
```

Built by `ProductPageData::configuratorOptions()` from `LinePricer`'s
ingredients (`CustomizationOption::priceFor` at the line quantity with the
article markup, `equivalentFor` on the first article). The three cascade
endpoints stay for the panel.

`/prodotti/configuratore/riepilogo` `{ articles, customizations, has_packaging, sample }` →
the `PricedLine` as JSON (articles with their customizations, fixed costs,
surcharge, totals, `unit_price`, `shipping`, `vat`, `total`, `shipping_date`).
The existing `articoli` endpoint (HTML lines) stays for the panel and the PDF.

### 4.2 Modal configurator (`storefront.configurator = modal`)

One Alpine component `productConfiguratorModal` in the core bundle,
rendered by `components/product/configurator-modal.blade.php` (the page
includes one or the other by config; skins may override either view).
Opened by "Calcola e acquista"; five accordion steps, headers turn primary
when open and show a check when done:

1. **Colori** — multi-select chips from the page data; auto-skipped when
   the product has one colour.
2. **Quantità** — one table per chosen colour (Codice articolo,
   Disponibilità, Stock, Restock, Quantità richiesta), one row per size,
   messages: "Seleziona una quantità superiore a :min", "Hai selezionato
   una quantità superiore al massimo disponibile", "…richiedi un
   preventivo per maggiori informazioni", "La quantità richiesta eccede
   quella attuale, la data di spedizione sarà quella della disponibilità
   futura". Footer question **"Vuoi personalizzare il prodotto applicando
   una stampa?"** with **SÌ / SÌ (richiedi preventivo) / NO (acquista
   direttamente)**; NO jumps to the summary; the middle one hands the
   selection to the quick quote (§4.4).
3. **Stampa** — the options tree of §4.1 arrives here. Technique buttons
   row, then a position select whose options are the priced
   area/option combinations for that technique (label "Fronte — 10x10 cm
   — 1 colore — 0,42 €/pz, impianto 30,00 €"), a preview image of the
   position, **"Aggiungi posizione"** which lists the chosen
   (technique, position, option) and lets the next choice use another
   technique — the owner's extension — plus a remove link per line. When
   the supplier has packaging, a "Confezionamento singolo" toggle.
4. **Grafica** — one file input per chosen position ("Carica il file",
   accepted types as the account upload) or **"Procedi senza file"**;
   files go to `storage/app/cart-artwork/{session}/…` through
   `/carrello/grafica` and follow the line into the order
   (`order_item_customizations.file`), the same place the account upload
   writes. Flag `artwork_in_configurator`.
5. **Riepilogo** — the pre-cart table: one row per article (SKU chip +
   colour square, "N ×", unit, subtotal), one row per customization
   ("Serigrafia Fronte — 1 colore"), one row per setup ("Costi impianto —
   Serigrafia", **"Omaggio"** when 0), start costs, surcharge; totals
   "Spese di trasporto" (from `Pricing::deliveryCost`), "Totale
   imponibile", "IVA 22 %", "Totale (IVA inclusa)", "Prezzo cad."; then
   **"Data di spedizione"** (§4.5) and "Aggiungi al carrello" (same
   `add_to_cart` payload plus `artwork` ids).

All prices come from `LinePricer`; the modal never computes money.

### 4.3 Product page and tabs

The product page keeps its data; the gesca84 layout is a skin override.
What the core adds so that the override can be light and fast:

- `components/product/tabs.blade.php`: Alpine tabs whose non-default
  panels fetch their HTML on first open from `/prodotti/{slug}/scheda/{tab}`
  (`dettagli` is rendered inline for SEO; `disponibilita` and `listino`
  are lazy, cached with the product page cache). Skins choose which tabs.
- `ProductVariant::hoverImage()` (second media thumb) and the card
  component renders it when `card_hover_image` is on.
- `products.quote_only` (boolean, default false; set by connectors or the
  admin): the product page shows the guard "Questo prodotto … disponibile
  solo in modalità preventivo" and only the quote CTA; cards show
  "Richiedi preventivo" only.

### 4.4 Quick quote modal (`storefront.quick_quote = modal`)

`components/quick-quote.blade.php` + Alpine `quickQuote`, mounted in the
layout once: the header pill and every "Chiedi un preventivo" open it.
Left: the contact fields of the quotation page (Nome, Cognome, Email*,
Azienda, Settore attività, Telefono, privacy*, newsletter). Right: the
products to quote (from the session `quotation.products`, the same store
the page uses), each with colour/size/quantity/personalizzazione/note,
add/remove. JSON endpoints under `/preventivo/api/` (list, add, update,
remove, send) wrap `FrontendQuotationController`'s existing logic;
`send` = `store()`. The product page CTA adds the current variant (and the
quantities from step 2 when the modal came from the configurator) before
opening. The quotation page stays for `quick_quote = page` and as the
no-JS fallback (`/preventivo`).

### 4.5 Shipping date (`storefront.shipping_date`)

`App\Support\ShippingDate::for(PricedLine $line, ?Carbon $from = null): Carbon`:
working days from `from` (today, or tomorrow after `delivery.cutoff_hour`)
+ `product->processing_days()` (connector days + default customization
days) + the line's customization days, skipping Saturdays, Sundays and
`delivery.holidays`; when an article's quantity exceeds the stock and a
restock date exists, the count starts from the restock date (the message
of step 2). `ImportConnector::shippingDate(Product, int $workingDays): ?Carbon`
lets a connector answer from the supplier's API later (null = core
rule). Shown in the modal summary, the cart line and the order mail.

### 4.6 Sample request (`storefront.samples`)

"Richiedi un campione" (Disponibilità tab, and the modal's step 2 when
quantities are below the minimum): a small modal with colour and size
selects, the price of one piece (tier 1, article markup), shipping, VAT,
total, shipping date, "Aggiungi al carrello". A sample is a cart line
`{articles: [[variant, 1]], customizations: [], sample: true}`: the pricer
skips the minimum surcharge for it, the cart shows "Campione", the order
item carries `is_sample` (new column) and the admin order view shows the
badge. No customization allowed on a sample line.

### 4.7 Onepage checkout (`storefront.checkout = onepage`)

`/checkout` renders a Livewire component `CheckoutOnepage` (server-side
state and validation, one request per step, no page reloads):

| Step | Content | Source |
|---|---|---|
| 1 Metodo di checkout | "Accedi" (email, password) / "Registrati" (the core registration fields, account created on completion of the step) | `attempt_login`, `register` logic |
| 2 Dati di fatturazione | `forms.customer-fields` (fiscal data, billing address), "Spedisci a questo indirizzo" checkbox | `account()` logic |
| 3 Indirizzo di spedizione | shipping address when different | `CustomerAddress` |
| 4 Metodo di spedizione | one radio "Corriere espresso — € x,xx" (free from the threshold) | `Pricing::deliveryCost` |
| 5 Pagamento | radio cards from `checkout.payment_methods` | `payment()` logic |
| 6 Riepilogo | the order table (articles, customizations, extras), totals, shipping date, consents, "Conferma ordine" | `store_order()` |

Right column "Il tuo checkout": the steps with a one-line summary of each
completed one and "Modifica". Completion calls the same `store_order`
path (bank transfer → registered page; gateway → redirect). The cart page
for the onepage flow is a table (Prodotto with options and customization
lines, Prezzo unitario, Quantità, Subtotale, remove), "Continua lo
shopping", "Svuota il carrello", totals box, "Procedi al checkout": a
skin-level layout over the same cart data, provided by the core as
`components/cart/table.blade.php` so that both flows share it.

### 4.8 Performance

Targets of ARCHITECTURE §12 apply to the gesca84 preview: the product
page renders gallery, title, price table and CTAs server-side; the modal
markup is a template instantiated on open; the options tree is fetched
once per quantity change; tabs are lazy; the quick-quote modal loads its
list on open; card hover images are `loading="lazy"`; the onepage
checkout is one Livewire component with `wire:loading` states. Bundle
budget stays 120 KB gzip (`PERF_BASELINE.md`); Lighthouse before/after
recorded there for the gesca84 preview.

## 5. Schema

One guarded, forward-only migration, folded into the dump as usual:
`products.quote_only` (bool default 0), `order_items.is_sample` (bool
default 0), `order_items.shipping_date` (date null). Artwork uses the
existing `order_item_customizations.file`.

## 6. Phases and stop criteria

**F1 — Foundations.** Config block, migration, `quote_only`, hover image,
lazy tabs component + fragment endpoints, options-tree and JSON-summary
endpoints, `ShippingDate`, cart-artwork upload endpoint and storage,
`sample` line in `LinePricer`/cart/order. Tests for each.
Stop: defaults unchanged (whole suite green as before); new endpoints and
helpers covered; demo untouched.

**F2 — Modal configurator.** The Alpine component and view, artwork step,
summary with shipping date, add to cart with artwork; product page picks
panel/modal by config.
Stop: with `MERCATURA_CONFIGURATOR=modal` the characterisation totals of
`LinePricingTest` are reproduced through the modal endpoints; a browser
test of the five steps on the demo catalogue; panel flow untouched.

**F3 — Quick quote modal.** Endpoints, component, header and CTA wiring.
Stop: a quote sent from the modal produces the same `Quotation` rows and
mails as the page.

**F4 — Sample request.** Modal, cart/order flag, admin badge.
Stop: a sample order is stored with `is_sample`, no minimum surcharge.

**F5 — Onepage checkout.** Livewire component, cart table component.
Stop: an order placed through the onepage flow equals one placed through
the steps flow (same rows, totals, mails), for bank transfer and a
gateway stub; the steps flow untouched.

**F6 — gesca84 skin and performance.** Skin overrides (product page
layout with tabs, cart table, category tiles, green band, SEO block,
about), env switches on, Lighthouse pass and fixes.
Stop: gesca84 preview at or above the core baseline scores; skin-check
clean; `docs/GESCA84_SKIN.md` updated with what was overridden.

## 7. Deviations recorded during execution

**F1 (2026-09-17).** As planned, plus: the cart artwork directory is keyed by a token kept in the session, not by the session id (the id changes at login); the options tree carries `markup_percent` and per-option `packaging_unit_price`; the summary JSON adds `taxable`, `vat_rate` and `processing_days`. `ProductPageData` exposes `detailsRows`, `packagingRows`, `priceTableRows`, `defaultCustomization`, `stockTable` and `configuratorOptions` for the sheet fragments.

**F2 (2026-09-17).** `productConfiguratorModal` (Alpine, core bundle) and
`components/product/configurator-modal`; the product page mounts panel or
modal by `storefront.configurator` and labels the CTA "Calcola e acquista"
for the modal. Step 3 offers every technique as a button row and one
select of priced position/area/option rows for the browsed technique;
"Aggiungi posizione" keeps one decoration per position, so different
positions may use different techniques (the owner's extension). The
"SÌ (richiedi preventivo)" answer sends to the quotation page with the
quantities in the URL hash until F3 wires the modal. No browser test yet:
the page test checks the mount and the copy, the endpoints are tested in
F1. Bundle: 119 KB gzip, at the budget.

**F3 (2026-09-17).** `SendQuotation` action holds what `store()` did
(rows, the two mails, newsletter); the page calls it too. JSON endpoints
under `/preventivo/api/` (`lista`, `aggiungi`, `{id}/aggiorna`,
`{id}/elimina`, `invia`) in `FrontendQuickQuoteController`, on the same
session keys as the page. `components/quick-quote` is mounted once by the
layout when `quick_quote = modal`; the header pill, the product CTA, the
price-table link, the card CTA and the modal configurator's "richiedi
preventivo" answer raise `quick-quote-open` with the article (and the
total quantity from the configurator). `captcha-init` is now `@once`, so
the modal and a page with its own form share one bootstrap. The quotation
page and its routes are untouched (no-JS fallback).

**F4 (2026-09-17).** `components/product/sample-request` + Alpine
`sampleRequest`, mounted by the product page when `samples` is on; it
prices one piece through the summary endpoint with `sample: 1` and
submits the cart line `{articles: [[variant, 1]], printings: [],
sample: 1}` (F1 already stored `is_sample` and the cart label). Opened by
`data-sample-request="<variant>"` buttons, so the one in the lazily
loaded Disponibilità fragment needs no Alpine wiring, and by the modal
configurator when a quantity is below the minimum. The admin order view
shows "Campione"/"Ordine" and the shipping date per item. Bundle: the
product-page components (both configurators, sample request) moved to a
lazy chunk loaded when the page has `#product`, before Alpine starts;
main bundle 117 KB gzip, chunk 3.8 KB.
