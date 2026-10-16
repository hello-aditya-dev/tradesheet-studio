# The DealPacket Workflow

A DealPacket is a **buyer-specific, versioned commercial offer** that keeps
the PDF catalog, the CSV order sheet, the protected online portal, the
negotiated quotation and the final WooCommerce order anchored to **one
immutable commercial snapshot**.

## Lifecycle diagram

```
                       merchant                      buyer                     system
                         │                             │                        │
   create ───────────► DRAFT ──────────────────────────┼────────────────────────┼──────► (delete drafts only)
                         │  compose lines, terms       │                        │
                    publish (preflight, snapshot,      │                        │
                    checksum, PDF/CSV/ZIP, share URL)  │                        │
                         ▼                             │                        │
                      ISSUED ─────────────────────────►│ open portal, pick qty  │
                         │                             │ or upload CSV          │
                         │◄────────────────────────────┘ submit quote request   │
                         ▼                             │                        │
                     SUBMITTED                         │                        │
                         │  merchant review             │                        │
                         ▼                              │                        │
                    IN_REVIEW                           │                        │
                         │  approve (integrity check)   │                        │
                         ▼                              │                        │
                     APPROVED ─────────────────────────►│ accept in portal       │
                         │                              │ (order is created     │
                         │◄─────────────────────────────┘  at acceptance)      │
                         ▼                                                      │
                    ACCEPTED ────── convert / buyer acceptance ──► ORDERED ─────┤
                                                                                 │
   from ANY live/terminal state:  EXPIRED / REVOKED / CANCELLED ──► ARCHIVED ◄──┘
```

Both state machines are documented exhaustively in
`docs/architecture/STATE_MACHINES.md`.

## Immutability and versioning

- **Draft** packets are freely editable (lines, terms, pricing). Draft lines
  live in `tss_packet_lines` with `version_id = NULL`.
- **Publishing** copies the draft into a version row
  (`tss_packet_versions`) whose `snapshot` column holds the full frozen
  commercial payload — products, variations, SKUs, names, descriptions,
  images, unit prices with price source, quantity rules, breaks, currency,
  validity window, company identity and the tax context hash — plus a
  SHA-256 `content_checksum`. Lines are copied with the version id.
- **Issued packets are immutable.** `POST /packets/{id}` on a non-draft
  returns *“Issued packets are immutable. Use Revise to create a new
  version.”* Line updates/deletes are draft-only.
- **Revising** seeds a *fresh draft* from the current version
  (`POST /packets/{id}/revise`). Publishing again creates **version N+1**
  and marks the previous version `superseded` — an issued version is never
  rewritten. Each version has its own documents and its own checksum
  (unique key `(packet_id, version_no)`).
- Every state change is appended to the audit trail (`tss_events`) with
  actor and timestamp.

## What the buyer sees and does

See `docs/product/BUYER_PORTAL.md` for the full portal guide. Summary: the
buyer opens `/tradesheet/packet/{REF}/?t=<token>` (or logs in as the owning
customer), sees the branded offer with frozen prices and quantity rules,
enters quantities in the portal or uploads a completed CSV order sheet, and
submits a **quote request**. Quote lines always take prices from the
server-side snapshot — buyer input is limited to quantities and a note.

## Quote flow and revisions

1. **Request** (`QuoteRepository::createRequest`) — validates quantities
   against the snapshot's min/step rules, applies quantity-break tiers from
   the snapshot, stores lines with `price_source`, runs the **integrity
   engine** and stores its report. One active quote chain per packet.
2. **Merchant review / approve / reject** — with optional notes. Approval
   walks the packet through its own legal transitions
   (submitted → in_review → approved).
3. **Revision** (`QuoteRevisionService`) — the merchant edits lines (price,
   quantity, notes); a NEW quote row is created with `revision_no = N+1`,
   chained via `parent_quote_id`; the parent becomes `superseded` and can
   never be accepted. The buyer must **re-accept the new revision** —
   acceptance of a superseded revision is refused.
4. **Acceptance** — the buyer accepts an APPROVED (or REVISED-state) quote
   in the portal; the quote becomes `accepted` and the packet walks to
   `accepted`.
5. **Conversion** (`WooCommerceOrderBridge`) — creates the WooCommerce
   order at the **negotiated line prices** (never the current public
   price), lets WooCommerce's tax engine compute taxes on those values, and
   stores an order link row keyed by quote id. **Idempotent:** concurrent or
   duplicate submissions return the same order (or `202 pending` while a
   conversion is in flight); an interrupted conversion is reconciled by a
   recovery sweep that searches WooCommerce for an order carrying the quote
   reference meta before allowing a retry.

## The integrity engine (findings)

At quotation time the frozen snapshot is compared against the **live**
WooCommerce state (`src/Quotes/QuoteIntegrityEngine.php`). Nothing is
silently substituted: blocking findings require a merchant decision
(honor / revise / reject) before buyer acceptance proceeds.

| Type | Severity | Meaning |
|---|---|---|
| `PRICE_CHANGED` | blocking | Live resolved price differs from the snapshot price |
| `PRODUCT_REMOVED` | blocking | Product no longer exists in the store |
| `VARIATION_REMOVED` | blocking | The quoted variation no longer exists |
| `SKU_CHANGED` | warning | The store SKU changed since the offer was issued |
| `PRODUCT_UNAVAILABLE` | blocking | Product exists but is not purchasable/published |
| `QUANTITY_RULE_VIOLATED` | blocking | A submitted quantity violates snapshot min/step rules |
| `TAX_CONTEXT_CHANGED` | warning | Store tax settings changed; taxes follow current store config |
| `CURRENCY_MISMATCH` | blocking | Store currency differs from the offer currency |
| `OFFER_EXPIRED` | blocking | Version validity passed, or the packet is no longer live |
| `INSUFFICIENT_STOCK` | warning | Live stock below the requested quantity |
| `QUOTE_SUPERSEDED` | blocking (checked at acceptance) | The quote was superseded by a newer revision |

## Preflight findings (before publishing)

`PreflightValidator` produces blocking errors (publication refused) and
warnings (allowed, recorded in the audit event). Blocking:
`CUSTOMER_MISSING`, `CURRENCY_CONTEXT_MISSING`, `CURRENCY_MISMATCH`,
`EMPTY_PACKET`, `VALIDITY_INVALID`, `PRODUCT_MISSING`,
`UNSUPPORTED_PRODUCT_TYPE`, `VARIATION_MISSING`, `PRICE_INVALID`,
`PRICE_OUT_OF_RANGE`, `PRICE_ZERO`, `QTY_RULE_INVALID`, `SKU_MISSING`,
`PRODUCT_UNAVAILABLE`, `OUT_OF_STOCK`, `DUPLICATE_LINE`. Warnings:
`PRICEBOOK_UNUSABLE`, `SKU_CHANGED`, `IMAGE_MISSING`. The same validator
re-runs server-side at publication time; the UI merely surfaces it earlier.

## Terminal conditions and cleanup

- `EXPIRED` — set by the hourly sweep when a version's `expires_at` passes
  (or manually). Buyer tokens for the packet are revoked.
- `REVOKED` — merchant withdrawal of an issued offer; tokens revoked.
- `CANCELLED` — abandoned before acceptance.
- `ARCHIVED` — the terminal sink; reachable from every other terminal
  state (and directly from ordered). Archived packets keep their audit
  history and documents.
- `ARCHIVED` is the only state with no outgoing transitions.

## Money guarantees

All commercial arithmetic goes through `src/Support/Money.php`: canonical
decimal strings (scale 4, matching `DECIMAL(19,4)` columns), integer-scaled
arithmetic, half-away-from-zero rounding only at presentation, bounds
(|value| ≤ 999,999,999.9999, qty ≤ 100,000) and driver-independent
canonicalization (`Money::fromDb`) so identical snapshots are produced on
MySQL/MariaDB and SQLite alike.
