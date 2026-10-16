# Quick Start — your first DealPacket in ~10 minutes

Goal: configure the plugin, build one buyer-specific offer, publish it and
share the buyer portal link. Assumes the plugin is installed and activated
(see [INSTALLATION.md](INSTALLATION.md)).

## Prerequisites checklist (2 min)

- WooCommerce is active and your store has at least a few products with
  prices (simple products and/or product variations — v1 supports these two).
- At least one WooCommerce **customer** account exists for your buyer
  (**WooCommerce → Customers**). A customer billing company name makes
  documents nicer but is optional.

## Step 1 — Company identity (1 min)

**WooCommerce → TradeSheet Studio → Settings**

Fill in company name, address, email, phone, and (optionally) upload a logo
and set a document footer. Leave the remaining defaults (30-day offer
validity, 14-day quote validity, 30-day buyer tokens, A4, `trade_table`).

## Step 2 — Create a price book and assign the buyer (2 min)

**Price Books → New Price Book**

1. Name it (e.g. "Electrical Wholesale — Tier A").
2. Add a rule:
   - Scope: pick a product (or a specific variation), or leave both empty
     for a book-wide rule.
   - Price: EITHER a fixed **price override** OR a **% off** the WooCommerce
     base price (never both).
   - Quantity: set **Min** and **Step**, and optionally quantity-break tiers
     (e.g. `10+ → 4180.00`, `50+ → 3950.00`). The highest reached tier
     **replaces** the line price.
3. **Customers → Assign**: pick your buyer and assign them to this book.

> Precedence, exactly one rule wins: packet line override → customer-specific
> rule → price-book rule (variation scope > product scope > book-wide, then
> higher priority, then lowest rule id) → WooCommerce base price. Details:
> `docs/product/PRICE_BOOKS.md`.

Use the book editor's **Preview tool** to sanity-check: choose customer +
product + qty and confirm the resolved price and its explanation.

## Step 3 — Compose the DealPacket (4 min)

**Sales Packets → Create Packet** — the guided five-stage composer:

1. **Buyer** — search and select the WooCommerce customer.
2. **Products** — search your catalog, add simple products or specific
   variations; drag to reorder.
3. **Pricing & terms** — each line resolves automatically through the price
   book; optionally set a per-line price override, minimum quantity and step.
   Fill commercial terms (payment terms, delivery terms, lead time, notes,
   minimum order value) and the offer's valid-until date.
4. **Documents** — choose the PDF template
   (`trade_table` table-style landscape, `visual_catalog` image-led portrait,
   `technical_sheet` spec-sheet portrait).
5. **Preflight & publish** — run preflight. Fix any blocking findings
   (missing product, currency mismatch, empty packet…); warnings are
   recorded but do not block.

Click **Publish**. This freezes an **immutable version snapshot**
(products, SKUs, names, prices, qty rules, currency, validity) with a SHA-256
content checksum, generates the PDF catalog, CSV order sheet and ZIP bundle,
and issues the buyer portal share URL.

## Step 4 — Share the offer (1 min)

On the packet detail screen:

- Copy the **share URL**
  (`https://example.com/tradesheet/packet/TSS-P-XXXX/?t=<token>`).
- Download the PDF / CSV / ZIP yourself via the documents panel.

Send the URL (and/or the PDF) to your buyer. The portal link token expires
after the configured *buyer token days*; regenerating the link revokes the
old token.

## Step 5 — What your buyer does

Opening the URL shows the branded portal: the offer's products, prices,
min/step rules and validity. The buyer can:

- set quantities directly in the portal and **request a quote**, or
- download the CSV order sheet, fill the **Qty** column offline, and
  upload it back — the upload is previewed, then confirmed.

Your job then: **Quotes** → open the request → review the integrity report
(price changes, removed products, SKU changes, expiry… — see
`docs/product/DEALPACKET_WORKFLOW.md`) → **Approve** (or issue a
**revision** with edited lines/prices). The buyer re-opens the portal and
**accepts**; the plugin creates the WooCommerce order at the **negotiated
prices**, idempotently (a double-click cannot create two orders).

## Where to go next

| Topic | Document |
|---|---|
| Every admin screen explained | `docs/product/USER_GUIDE.md` |
| Full DealPacket lifecycle, revisions, integrity findings | `docs/product/DEALPACKET_WORKFLOW.md` |
| CSV order-sheet format for buyers | `docs/product/CSV_FORMAT.md` |
| REST API (namespace `tradesheet-studio/v1`) | `docs/architecture/API_REFERENCE.md` |
| Something broken? | `docs/installation/TROUBLESHOOTING.md` |

> The demo store used in development ("Volt & Wire Trading Co." with buyers
> "Krishna Electricals"/"Shakti Industries") is entirely **synthetic data**
> used for testing and screenshots — it is not real business data.
