# Price Books

Price books hold your wholesale pricing rules and are assigned to WooCommerce
customers (one book per customer). The deterministic engine
(`src/Pricing/PricingEngine.php`) resolves **exactly one winning price** per
product/variation/quantity — no silent stacking.

## The rule model

A rule (`tss_price_rules`) has:

| Field | Meaning |
|---|---|
| `pricebook_id` | The owning book |
| `customer_id` | `0` = book-wide rule; `> 0` = customer-specific rule inside the book |
| `product_id` / `variation_id` | `0`/`0` = book-wide; `product_id > 0` = product-scoped; `variation_id > 0` = variation-scoped (most specific) |
| `price_override` | Fixed price (decimal string, ≤ 4 dp) — EITHER this OR `percent_off`, never both |
| `percent_off` | Percentage discount off the WooCommerce base price (scale 2) |
| `min_qty` / `qty_step` | Minimum quantity and step multiples for orders |
| `qty_breaks` | JSON tiers: `[{"qty":10,"price":"4180.0000"},{"qty":50,"price":"3950.0000"}]` |
| `priority` | Tie-break after scope specificity |
| `valid_from` / `valid_to` | Rule validity window |

A rule may set *only quantity constraints* (no price fields) — then the
price is unchanged and only min/step apply.

## Precedence — the exact order

```
1. Packet line price override      (set in the composer; frozen into the snapshot)
2. Customer-specific rule          (customer_id > 0, in the customer's assigned book)
3. Price-book rule                 (customer_id = 0, same book)
4. WooCommerce base (active) price
```

Within levels 2–3, **one** winner is picked by this comparator
(`PricingEngine::pickWinner`):

```
customer-specific  >  variation-scoped  >  product-scoped  >  book-wide,
then  priority (higher first),  then  lowest rule id (stable tie-break)
```

The winner is the ONLY rule applied. Percent discounts compute off the
WooCommerce base price (`base − percent(base)`), not off other rules.

## Quantity breaks

The winner's qty-break tiers **replace** the winner price when the requested
quantity reaches a threshold — the highest reached tier applies:

| Qty | Price |
|---|---|
| — (base) | resolved rule price (e.g. 4450.00) |
| 10+ | 4180.00 |
| 50+ | 3950.00 |

Qty=4 → 4450.00; qty=10 → 4180.00; qty=50 → 3950.00. Breaks are stored on
the winning rule and copied into the packet snapshot; at quotation time the
tier is re-derived from the snapshot via `PricingEngine::effectiveUnitPrice`.

## Book usability

A book is usable when it is `active` AND inside its `valid_from`/`valid_to`
window. An unusable book's rules are skipped entirely and resolution falls
back to the WooCommerce base price. Assigning an unusable book to a packet
produces a preflight **warning** (`PRICEBOOK_UNUSABLE`), not an error.

## Assignments

- `POST /pricebooks/{id}/customers` assigns a WooCommerce customer
  (user meta `_tss_pricebook_id`).
- `DELETE /pricebooks/{id}/customers/{customerId}` unassigns (acts on the
  customer's current book; the book id in the path is the reference).
- One book per customer. Customer search lives in the Customers admin screen
  and `GET /customers` (CatalogController) — the price book assignment shows
  there per row.

## The preview tool

`GET /pricebooks/{id}/preview?customer_id=&product_id=&variation_id=&qty=`
runs the REAL resolution (`PricingEngine::resolveForCustomer`) and returns:

| Field | Meaning |
|---|---|
| `unit_price` | The resolved canonical price |
| `source` | `base` / `customer_rule` / `pricebook_rule` / `…_qty_break` |
| `rule_id` | Winning rule id (null for base) |
| `min_qty`, `qty_step` | From the winning rule (1/1 for base) |
| `explanation` | Human sentence, e.g. “Base price less 5% discount (rule #12).” |
| `warnings` | e.g. product has no WooCommerce base price |
| `qty_breaks` | The winner's tiers |

## Conflict behavior (worked examples)

Rules in book B (priority default 0 unless noted):

| # | Scope | Rule |
|---|---|---|
| 5 | book-wide | 10% off |
| 12 | product 101 | fixed 4399.0000 |
| 18 | product 101, priority 10 | fixed 4600.0000 |
| 21 | product 101 **variation 77** | 12% off |
| 30 | customer 9 × product 101 | fixed 4200.0000 |

- Customer 9 asks for product 101 → **rule 30 wins** (customer-specific
  outranks everything else): 4200.0000.
- Any other customer, variation 77 → **rule 21** (variation scope beats
  product scope and book-wide): base − 12%.
- Any other customer, product 101 (no variation) → **rule 18** (product
  scope, then priority 10 > 0): 4600.0000 — even though rule 12 is cheaper.
  Exactly one deterministic winner; there is no "lowest price" magic.
- Nobody assigned to book B → WooCommerce base price.

The same comparator is covered by the standalone unit suite
(`tests/unit/run.php` — precedence and tie-breaks, 105 assertions total).

## Where resolution happens

- Composer “add line” (`POST /packets/{id}/lines`) — resolves and freezes
  the price into the draft line (`price_source` recorded), unless an
  explicit `override_price` is given (then `packet_override`, level 1).
- Quote creation from the portal — prices come from the **snapshot**, never
  from the browser; qty-break tiers re-apply server-side.
- Quote revisions — merchant-entered prices only.

Money is computed with scale-4 decimal strings / integer-scaled arithmetic
(`src/Support/Money.php`); percent inputs use scale 2.
