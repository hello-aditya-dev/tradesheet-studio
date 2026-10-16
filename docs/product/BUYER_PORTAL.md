# Buyer Portal

The buyer portal is a self-hosted, responsive page at:

```
https://example.com/tradesheet/packet/{REF}/          e.g. /tradesheet/packet/TSS-P-AB12/
```

served from a standalone template (`templates/buyer/portal.php` + scoped
`assets/buyer.js`) — it does **not** load the theme's page chrome and does
not expose prices to anyone without access.

## Access model

A request is granted in exactly one of three modes
(`src/Buyers/BuyerAccessService.php`):

| Mode | Condition |
|---|---|
| `merchant` | Logged-in user with `manage_woocommerce` |
| `owner` | Logged-in as the WooCommerce customer the packet belongs to |
| `token` | Valid, non-revoked, non-expired share token in `?t=` (or POST `t`) |

Everything else — unknown packet, invalid token, expired token, revoked
token, wrong visitor — gets the **same generic 404-style denial**
(anti-enumeration: an attacker cannot distinguish "packet exists" from
"token wrong").

### Token facts (for merchants)

- Tokens are 64 hex characters from `random_bytes(32)`; **only the SHA-256
  hash is stored** (`tss_buyer_tokens.token_hash`, unique key).
- The raw token is returned exactly once when you click **Share**
  (`GET /packets/{id}/share`). It cannot be retrieved later from storage.
- **Regenerating a share link revokes the previous token** for that packet.
- Tokens expire after *buyer token days* (Settings; default 30).
- **Revoking or expiring the packet revokes all its tokens** immediately.
- Token usage is counted (`use_count`, `last_used_at`) for audit.
- Retention: revoked/expired tokens are purged after one year by the sweep.

### CSRF model

- **Token mode:** possession of the token IS the bearer capability — POSTs
  from the portal carry the token; no nonce is required.
- **Owner/merchant (cookie-authenticated) mode:** POSTs must include the
  WordPress nonce (`tss_nonce`, action `tss_buyer_{packetId}`); invalid
  nonce → redirect with a security error.

## What the buyer sees

- Your company identity (name, address, logo) from Settings.
- The offer header: packet reference, title, **version number**, issue date
  and validity window — the page always states which frozen version it
  reflects.
- The product table: SKU, name, variation summary, unit price
  (with quantity-break tiers), minimum quantity and step.
- Download buttons for the PDF catalog and CSV order sheet (ZIP bundle when
  available) — permission-checked streaming, never direct file URLs.
- The active quote card once a request exists (status, totals, and — after
  merchant approval — an **Accept** action).

All responses send `Cache-Control: no-cache` (via `nocache_headers()`),
`X-Robots-Tag: noindex, nofollow` and `Referrer-Policy: no-referrer`.

## What the buyer does

### 1. Enter quantities directly

The buyer fills the Qty column (validated client- and server-side against
min/step), optionally adds a note, and submits a **quote request**.
Only quantities and the note are honored — **prices always come from the
server-side snapshot**.

### 2. Upload a completed CSV order sheet

The buyer downloads the CSV, fills the `Qty` column offline, and uploads the
file:

1. Upload (max **2 MiB**, at least 12 bytes) → parsed with `fgetcsv`
   (no formula interpretation, rows capped at 2000).
2. Every row is validated against the **immutable server-side version**
   (`CsvOrderSheetService::validateAgainstVersion`): row ids must exist in
   this version, no duplicates, quantities must match min/step; SKU/name
   mismatches produce warnings (packet values win). The `UnitPrice` column
   in the file is **informational only** — never used.
3. A **preview** is shown; the buyer confirms.
4. Confirming creates the quote request exactly like the manual path.

Full file format: `docs/product/CSV_FORMAT.md`.

### 3. Accept an approved quote

When the merchant approves (or issues a revision the buyer is happy with),
the portal shows an **Accept** action on the quote card. Accepting:

- marks the quote `accepted`,
- **creates the WooCommerce order at the negotiated line prices**
  (idempotently — clicking twice, or two concurrent requests, cannot create
  two orders; the second submission returns the same order),
- redirects with the order number confirmation.

After conversion the buyer sees the order reference; payment follows your
normal WooCommerce flow (the pay URL is available to the merchant in the
quote detail).

## Security notes for merchants (summary)

| Control | Implementation |
|---|---|
| Hashed tokens at rest | `BuyerAccessService` (SHA-256, unique key) |
| Anti-enumeration | Identical generic 404-style denials |
| Rate limiting | submit 10/hour, upload 10/hour, accept 20/hour per token hash or user |
| No caching / no indexing | `nocache_headers()`, `X-Robots-Tag`, `Referrer-Policy` |
| Prepared statements everywhere | all SQL via `$wpdb->prepare()` |
| CSV uploads strictly untrusted | quantities only; server-side re-validation |
| Documents never publicly linkable | private storage + permission-checked streaming |
| Revocation | packet revoke/expire kills tokens; re-share revokes the old token |

Full analysis: `docs/security/THREAT_MODEL.md`.

## What creates an order (and what does not)

| Buyer action | Order created? |
|---|---|
| Submitting a quote request | No — creates a REQUESTED quote |
| Uploading / confirming CSV | No — same as a request |
| Accepting an **approved** quote | **Yes** — idempotent conversion |
| Accepting a **superseded** revision | Refused |
| Anything on an expired/revoked packet | Refused (portal denies access) |
