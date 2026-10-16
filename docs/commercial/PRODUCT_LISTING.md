# Product Listing (store copy)

Marketing copy for the product page and channel listings. Claims are
constrained to executed evidence — compatibility wording must mirror
`docs/testing/COMPATIBILITY_MATRIX.md` (never claim untested versions).

## Title

**TradeSheet Studio — Wholesale DealPackets for WooCommerce**

Tagline: *The wholesale catalog that becomes an order.*

## Short description (≤ 160 words)

> Send one authoritative offer per buyer. TradeSheet Studio turns your
> WooCommerce catalog into buyer-specific **DealPackets** — a versioned,
> immutable commercial offer that keeps a branded PDF catalog, a CSV order
> sheet, a protected buyer portal, quotation revisions and the final
> WooCommerce order anchored to **one snapshot**. Prices, terms and product
> data can never silently diverge between what you sent, what you quoted
> and what you fulfill.

## Long description

Wholesale teams lose margin in the gap between the catalog they email, the
quote they negotiate and the order they enter. TradeSheet Studio closes
that gap with a guided workflow:

1. **Price books** — fixed overrides, percentage discounts, quantity breaks,
   minimum quantities; assign each WooCommerce customer to a book. One
   deterministic winner per product — documented precedence, no silent
   stacking.
2. **DealPacket composer** — five guided stages: buyer → products &
   variations → pricing & commercial terms → document presentation →
   preflight validation & publish.
3. **Immutable snapshots** — publishing freezes products, SKUs, prices,
   quantity rules, currency and validity with a SHA-256 content checksum.
   Changes create a *new version*; issued offers are never rewritten.
4. **Documents** — three PDF templates (commercial table, visual catalog,
   technical sheet), a CSV order sheet and a ZIP bundle, generated for each
   version and served through protected, token-gated endpoints.
5. **Buyer portal** — a self-hosted page where the buyer adjusts
   quantities, uploads a completed CSV and requests a quotation. Tokens are
   hashed at rest, expiring and revocable.
6. **Quote integrity engine** — before anything becomes an order, the
   plugin compares the frozen offer against the live store: price changes,
   removed products or variations, SKU changes, quantity-rule violations,
   expired offers and more — blocking findings require a merchant decision.
7. **Quote-to-order bridge** — the buyer accepts the approved revision and
   the plugin creates the WooCommerce order at the **negotiated** line
   prices, idempotently. Duplicate or concurrent submissions cannot create
   two orders.

**Honest engineering:** exact decimal money arithmetic (no floating-point
pricing), prepared SQL everywhere, private document storage with
permission-checked streaming, CSV formula-injection neutralization in both
directions, rate-limited public endpoints, an append-only audit trail, and
WordPress/WooCommerce personal-data export & erasure integration. Tested
with WordPress 7.1.3 + WooCommerce 11.2.1 + PHP 8.4.5 in both legacy and
HPOS order storage — full compatibility matrix published in the
documentation.

## Feature list (bullets)

- Buyer-specific, versioned offers with SHA-256 content checksums
- Price books: overrides, % discounts, qty breaks, min/step rules, validity
  windows, customer assignment, live price preview with explanation
- Guided 5-stage composer with preflight validation
- 3 PDF catalog templates + CSV order sheet + ZIP bundle per version
- Token-gated buyer portal (hashed tokens, expiry, revocation,
  anti-enumeration, no-cache/no-index)
- CSV upload with server-side re-validation (prices never taken from the
  file; formula-injection safe both ways)
- Quote revisions with supersession — old revisions can never be accepted
- Integrity engine with blocking vs warning findings
- Idempotent quote→order conversion at negotiated prices (HPOS compatible)
- Append-only audit trail + Diagnostics health screen
- No telemetry; no third-party SaaS at runtime; bundled PDF renderer
  (LGPL/MIT, advisory-clean Dompdf 3.1.6)

## Requirements block (must be stated verbatim-ish)

- WordPress 6.4+ · WooCommerce 8.0+ · PHP 8.1+ (8.2 recommended) with
  `mbstring` and `dom`
- Works with legacy order storage **and** HPOS
- Executed test evidence: WordPress 7.1.3, WooCommerce 11.2.1, PHP 8.4.5
  (both order-storage modes). Other version combinations are untested —
  see the published compatibility matrix.

## Screenshot captions (7 screenshots — owner must capture from the demo store)

All screenshots must come from the demo installation using the synthetic
"Volt & Wire Trading Co." data (clearly labeled as demo). Suggested
captures:

1. **Composer — stage 3 (Pricing & terms):** the five-stage composer with
   products added, resolved wholesale prices and price-source badges
   visible; terms panel on the right. Caption: *“Guided composer — prices
   resolve automatically from the buyer's price book.”*
2. **Price book editor:** rules table (override / % off / qty breaks) with
   the live preview panel showing a resolved price and its explanation.
   Caption: *“Price books with quantity breaks and a live resolution
   preview.”*
3. **Buyer portal (browser view):** the branded portal with the offer
   table, ₹ prices, min/step hints and the CSV upload card. Caption:
   *“The buyer portal your customers open with one secure link.”*
4. **Integrity report (quote detail):** a quote with a blocking
   `PRICE_CHANGED` finding and a warning row, severity badges visible.
   Caption: *“The integrity engine flags every divergence before an order
   is created.”*
5. **PDF catalog (page 1 of `trade_table` landscape):** company header,
   buyer block, price table with totals and validity. Caption: *“A real
   PDF catalog — three templates, generated per version.”*
6. **Packet detail — versions & documents:** version list with checksums
   (one superseded) and the documents panel (PDF/CSV/ZIP with sizes).
   Caption: *“Immutable versions — every change is a new, checksummed
   version.”*
7. **Diagnostics screen:** the checks grid all-green on the demo store.
   Caption: *“Built-in diagnostics — real environment checks, no
   guesswork.”*

Capture notes: 1600px-wide PNGs; blur nothing (all data is synthetic);
keep the admin in English; include the state badges where relevant.

## Demo script (5 minutes, live or recorded)

1. *(30s)* Show the WooCommerce store's normal retail price of the LED
   High Bay (₹5,199) — the “catalog price”.
2. *(60s)* Open **Price Books** → assign buyer *Krishna Electricals* to
   *Tier A* → use the **preview tool** on the high bay: customer-specific
   ₹4,399 with the explanation sentence.
3. *(90s)* **Create Packet** — walk the five stages quickly; preflight
   shows a warning (unusable/inactive book or missing image) and no
   blockers; **Publish**; show the generated PDF and the share URL.
4. *(60s)* Open the portal URL in another tab as the buyer — set quantities
   (min 2/step 2 enforced), qty-break tier applies at 10+; submit quote.
5. *(60s)* Back in admin: **Quotes** — the request arrived with an
   integrity report; change the product's WooCommerce price to simulate
   drift, re-request → blocking `PRICE_CHANGED` finding appears; issue a
   **revision** honoring the old price → approve.
6. *(30s)* Buyer tab → **Accept** → show the WooCommerce order created at
   the negotiated ₹4,399 line price; click accept again → same order
   (idempotency).

All demo entities are synthetic; state that explicitly in recordings.

## Pricing copy

Launch **$49** · regular **$79** (hypothesis and GPL-aware positioning:
`docs/commercial/PRICING_HYPOTHESIS.md`). Copy line:
*“You're buying convenient packaging, documentation, updates and support —
the GPL freedoms of the included software remain.”*
