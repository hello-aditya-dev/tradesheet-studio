# User Guide — every admin screen

The merchant interface lives at **WooCommerce → TradeSheet Studio**
(a `manage_woocommerce` submenu — administrators and shop managers). It is a
compiled React application (`assets/admin.js`) that mounts into
`#tss-admin-root`, uses WordPress globals for React (`window.wp.element`) and
talks exclusively to the REST API in namespace `tradesheet-studio/v1`
(`docs/architecture/API_REFERENCE.md`).

The sidebar groups nine views:

```
Workspace   Overview
            Sales Packets
            Quotes
Catalog     Price Books
            Customers
System      Settings
            Diagnostics
            Help
```

## State badges (used throughout)

**Packet states** (see `docs/architecture/STATE_MACHINES.md` for the full
transition tables):

| Badge | Meaning |
|---|---|
| DRAFT | Editable packet; nothing shared with the buyer yet |
| ISSUED | Published (version 1+); buyer portal live; snapshot immutable |
| SUBMITTED | Buyer requested a quote from the live packet |
| IN_REVIEW | Merchant review started |
| APPROVED | Quote approved for buyer acceptance |
| ACCEPTED | Buyer accepted; ready to convert |
| ORDERED | WooCommerce order created and linked |
| EXPIRED / REVOKED / CANCELLED / ARCHIVED | Terminal conditions; portal denies access (archived is the terminal sink reachable from every terminal state) |

Version-level badge: **SUPERSEDED** — an older version of the same packet
that was replaced by a newer published version (never rewritten).

**Quote states:** REQUESTED → IN_REVIEW → REVISED / APPROVED → ACCEPTED →
ORDERED, plus REJECTED, SUPERSEDED, EXPIRED, CANCELLED (terminal).

Integrity badge colors on quotes: **blocking** findings (must be resolved
before conversion) vs **warning** findings (informational).

---

## Overview

Dashboard metrics from `GET /dashboard`: packet counts by state, quote
counts, order conversions, failed background jobs, plus the 25 most recent
events (object type/id, action, actor, timestamp) from the audit trail
(`tss_events`). Use it as your daily triage screen: *failed jobs* non-zero →
check Diagnostics; new submissions → jump to Quotes.

## Sales Packets (list)

- Table of packets: reference (`TSS-P-XXXX`), title, buyer, status badge,
  currency, published indicator (derived from `current_version_id`), validity,
  created/updated timestamps.
- Filters: status, buyer, free-text search; paginated (`GET /packets`).
- **Create Packet** button → the five-stage composer (below).
- Row click → packet detail.

### Create Packet (composer — five stages)

1. **Buyer** — search WooCommerce customers (`GET /customers`, includes
   billing company and currently assigned price book).
2. **Products** — search the catalog (`GET /products`), expand variable
   products to pick specific variations (`GET /products/{id}/variations`),
   add lines, reorder.
3. **Pricing & terms** — lines resolve through the pricing engine (see
   `docs/product/PRICE_BOOKS.md`); optional per-line override price
   (non-negative, ≤ 4 decimals), minimum quantity and step; commercial terms
   (payment terms, delivery terms, lead time, notes, minimum order value —
   each ≤ 2000 chars); currency (defaults to the store currency — v1 does not
   support multicurrency); valid-until date; optional price book pinning.
4. **Documents** — choose template: `trade_table` (landscape table),
   `visual_catalog` (image-led portrait), `technical_sheet`
   (specification portrait).
5. **Preflight & publish** — `GET /packets/{id}/preflight` lists blocking
   errors and warnings; **Publish** (`POST /packets/{id}/publish`) freezes
   the immutable snapshot (SHA-256 checksum), creates version 1, generates
   PDF + CSV (+ ZIP when the `zip` extension is available) and returns the
   documents and share URL.

### Packet detail

Cards/tabs:

- **Header** — reference, title, buyer, status badge, currency, price book,
  validity, version number and content checksum of the current version.
- **Actions** (state-gated): Share link (issues/refreshes the buyer token),
  Revise (seeds a new draft from the current version → version N+1),
  Regenerate documents, Revoke / Expire / Archive / Cancel transitions,
  Delete (drafts only — issued packets must be revoked/archived).
- **Lines** — the frozen version lines (or the editable draft lines before
  publishing): SKU, name, variation summary, qty min/step, unit price with
  price source (`base`, `customer_rule`, `pricebook_rule`,
  `…_qty_break`, `packet_override`), quantity-break tiers.
- **Versions** — every published version: number, status, checksum,
  issued_at, expires_at, issued_by. Older versions show SUPERSEDED.
- **Documents** — generated artifacts with type, template, size, timestamp
  and download links (permission-checked streaming).
- **Quotes** — quote chain for this packet with revision numbers, statuses,
  blocking/warning counts, converted order id.
- **Terms** — the commercial terms stored on the packet.
- **Events** — audit timeline (`GET /packets/{id}/events`, last 200).

## Price Books

- **List** — books with rule count, customer count, status
  (active/inactive), currency, priority, validity window. Create,
  duplicate, delete.
- **Book editor**:
  - *Rules CRUD* — scope (customer-specific, product, variation, or
    book-wide), EITHER fixed price override OR percent-off, min qty, qty
    step, qty-break tiers (qty threshold + price), rule priority, validity
    window. Upsert is keyed on the (book, customer, product, variation)
    scope — re-submitting the same scope replaces that rule.
  - *Customer assignment panel* — assign/unassign WooCommerce customers
    (one book per customer).
  - *Live preview tool* — pick customer + product/variation + quantity →
    `GET /pricebooks/{id}/preview` returns the resolved price, its source,
    the winning rule id, applied qty-break tier and a human explanation.

Full model and precedence: `docs/product/PRICE_BOOKS.md`.

## Quotes

- **List** — quote references (`TSS-Q-XXXX`), packet ref/title, revision
  number, buyer, status badge, blocking/warning counts, requested/approved/
  accepted timestamps, converted order id. Filter by status; paginated.
- **Detail**:
  - Lines with quantities, unit prices, price source, per-line merchant
    notes and computed line totals + grand total (scale-4 decimal strings).
  - **Integrity report** — the structured discrepancy list produced when the
    request arrived (types and severities in
    `docs/product/DEALPACKET_WORKFLOW.md`).
  - **Actions** (state-gated): Review (→ in_review), Approve (optionally
    with notes), Reject (with notes), Revise (line editor: change
    prices/quantities/notes → creates a new REVISION row, supersedes the
    previous one), Convert (creates the WooCommerce order at negotiated
    prices — 201 created / 202 pending / idempotent replay returns the same
    order).
  - **Revision diff** — for revisions, the changes vs the parent revision
    (added / removed / price / qty per row id).
  - **Order & pay links** — after conversion, links into the WooCommerce
    order and customer payment URL.

## Customers

Customer search (role `customer`) with per-row price-book assignment
(assign/unassign). Shows display name, email, billing company, current book.
(`GET /customers`, `POST /pricebooks/{id}/customers`,
`DELETE /pricebooks/{id}/customers/{customerId}`.)

## Settings

All 15 merchant settings (validated and sanitized server-side in
`src/Settings/MerchantSettingsService.php`):

company name/address/email/phone, logo id, document footer, default validity
days (1–730), quote validity days (1–730), buyer token days (1–730),
document size (A4/Letter), default template, file retention days (0–3650,
0 = keep forever), notify emails (on/off), and the red-flagged
**Delete all TradeSheet Studio data on uninstall** switch (default off —
data is preserved by default).

## Diagnostics

A grid of live environment checks (PHP version, WordPress version,
WooCommerce version, HPOS mode, Action Scheduler, task scheduling health,
private document storage writability, PDF renderer availability, PHP
extensions mbstring/dom/zip/gd, free disk space, failed background jobs)
plus plugin version and schema version. Interpretation guide:
`docs/installation/TROUBLESHOOTING.md`.

## Help

In-app orientation: workflow recap, composer guide, packet & quote state
legends, a pointer to this documentation folder, and the support note.

---

## Frequently used actions and what they actually do

| Action | Effect (server-side) |
|---|---|
| Publish | Freeze snapshot + checksum; draft→issued; generate documents; audit event |
| Revise (packet) | Seeds a fresh editable draft from the current version; publishing later creates version N+1 and marks the old version superseded |
| Share | Issues a fresh 64-hex token (SHA-256 at rest), revokes the previous one, returns the portal URL |
| Revoke / Expire | Moves the packet to a terminal state AND revokes all buyer tokens for it |
| Approve (quote) | quote → approved; packet walks submitted→in_review→approved (legal single-hop transitions) |
| Revise (quote) | New revision row chained via `parent_quote_id`; previous revision → superseded (can never be accepted) |
| Convert | Idempotent WooCommerce order creation at negotiated line prices; order link row keyed by quote id |
