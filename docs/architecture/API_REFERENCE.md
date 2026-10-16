# REST API Reference

- **Namespace:** `tradesheet-studio/v1` (base: `/wp-json/tradesheet-studio/v1`)
- **Registration:** `src/Api/Routes.php` → five controllers: Catalog,
  PriceBooks, Packets, Quotes, System.
- **Permissions:** every route declares `permission_callback` =
  `Capabilities::restCanManage` → the `manage_woocommerce` capability
  (administrators/shop managers; filterable via the `tss_manage_capability`
  filter). Cookie-authenticated state-changing requests additionally need a
  valid `X-WP-Nonce` header (enforced by the WP REST infrastructure).
- **Conventions:** money values are canonical decimal strings (scale 4);
  timestamps are `YYYY-MM-DD HH:MM:SS` (site/UTC as stored by the plugin);
  errors are `WP_Error` objects with `code` and HTTP `status`.

Common errors: `401 rest_forbidden` (unauthenticated), `403` (missing
nonce for cookie-auth), `400 tss_validation` (business validation),
`404 tss_not_found`, `500 tss_error` (unexpected).

## Catalog (`src/Api/CatalogController.php`)

### GET /products
Merchant product search for the composer.
Query args: `search` (string), `page` (int, default 1), `per_page` (int,
default 20), `category` (int, default 0).
Response `200`: `{ items: [...], total: int, currency_symbol: string }`.

### GET /products/{id}/variations
Variations of a variable product. Path: `id` (int).
Response `200`: `{ items: [...] }`.

### GET /customers
Customer search (role `customer`). Query args: `search` (string, wildcard
search), `page` (int, default 1), `per_page` (int, default 25, max 100).
Response `200`: `{ items: [{id, display_name, email, billing_company,
pricebook_id}], total }`.

## Price books (`src/Api/PriceBooksController.php`)

### GET /pricebooks
Response `200`: `{ items: [{…book fields, rule_count, customer_count}] }`.

### POST /pricebooks
Body: `name` (required, non-empty), `description`, `status` (default
`active`), `currency` (3 letters, uppercased), `priority` (int),
`valid_from`, `valid_to` (`YYYY-MM-DD` → midnight, or full datetime).
Response `201 { id }`. Errors: `400 tss_validation` (empty name).

### GET /pricebooks/{id}
Response `200`: `{ book, rules: [...], customers: [...] }`. Errors: `404
tss_not_found`. Note: `qty_breaks` is decoded JSON in responses.

### POST /pricebooks/{id}
Partial update. Null-valued fields are filtered out server-side — sending
an empty `valid_from`/`valid_to` does NOT clear stored dates (known UI
limitation). Response `200 { updated: true }`. Errors: `404`.

### DELETE /pricebooks/{id}
Response `200 { deleted: true }`. Errors: `404`. Logs a `pricebook/deleted`
event.

### POST /pricebooks/{id}/duplicate
Response `201 { id }` (new book id). Errors: `404` when the source is
missing.

### GET /pricebooks/{id}/rules
Response `200`: `{ items: [rule rows with decoded qty_breaks] }`.

### POST /pricebooks/{id}/rules
Upsert a rule. Body (JSON preferred): `customer_id`, `product_id`,
`variation_id`, `price_override` XOR `percent_off`, `min_qty`, `qty_step`,
`qty_breaks` (array or JSON string), `priority`, `valid_from`, `valid_to`.
Upsert is keyed on the (book, customer, product, variation) **scope**, not
the rule id — resubmitting a scope replaces it. Response `201 { id }`.
Errors: `400 tss_validation`, `404`.

### DELETE /pricebooks/{id}/rules/{ruleId}
Response `200 { deleted: bool }`.

### GET /pricebooks/{id}/preview
Query args: `customer_id`, `product_id`, `variation_id` (default 0), `qty`
(min 1, default 1). Runs the real pricing engine.
Response `200`: `{ unit_price, source, rule_id, min_qty, qty_step,
explanation, warnings[], qty_breaks[] }`.

### GET /pricebooks/{id}/customers
Response `200`: `{ items: [{id, display_name, email, billing_company}] }`.

### POST /pricebooks/{id}/customers
Body: `customer_id`. Response `200 { assigned: true }`. Errors: `400`
(customer not found), `404` (book not found). Logs `customer_assigned`.

### DELETE /pricebooks/{id}/customers/{customerId}
Unassigns the customer from their current book. Response `200
{ unassigned: true }`.

## Packets (`src/Api/PacketsController.php`)

### GET /packets
Query args: `status` (default ''), `customer_id` (default 0), `search`
(string), `page` (1), `per_page` (20).
Response `200`: `{ items: [packet summaries], has_more: bool }` (has_more =
full page returned).

### POST /packets
Body: `customer_id` (required, existing user), `title` (required),
`pricebook_id` (optional, must exist), `currency`, `valid_until`,
`terms` (array; allowed keys `payment_terms`, `delivery_terms`,
`lead_time`, `notes`, `min_order_value`; ≤2000 chars each).
Response `201 { id, ref }`. Errors: `400 tss_validation`.

### GET /packets/{id}
Response `200`: `{ packet, lines, versions, current_version, documents,
quotes, terms }`. Draft packets return draft lines; issued packets return
the current version's frozen lines. Errors: `404`.

### POST /packets/{id}
Draft-only edit of `title`, `valid_until`, `pricebook_id`, `currency`,
`terms`. Issued packets: `400` — *“Issued packets are immutable. Use Revise
to create a new version.”* Response `200 { updated: true }`.

### DELETE /packets/{id}
Drafts only. Response `200 { deleted: true }`; else `400`.

### POST /packets/{id}/lines
Add a draft line. Body: `product_id`, `variation_id`, `qty_min`, `qty_step`,
`override_price` (optional non-negative decimal; when set the line price is
the override with `price_source = packet_override` and no breaks; otherwise
resolved via the pricing engine with `price_source = resolved`).
Response `201 { line, resolution }`. Errors: `400` (non-draft, missing
product, unsupported type, bad price), `404`.

### POST /packets/{id}/lines/{lineId}
Draft line update: `qty_min`, `qty_step`, `override_price`, `sort_order`.
Response `200 { updated }`.

### DELETE /packets/{id}/lines/{lineId}
Response `200 { deleted }`.

### POST /packets/{id}/lines-order
Body: `order` = array of line ids (new sort order).
Response `200 { reordered: true }`.

### GET /packets/{id}/preflight
Response `200`: `{ errors: [...], warnings: [...] }` (finding codes in
`docs/product/DEALPACKET_WORKFLOW.md`).

### POST /packets/{id}/publish
Body: optional `template` (must be one of `trade_table`,
`visual_catalog`, `technical_sheet`). Freezes the snapshot, creates the
version, generates documents. Response `201`: `{ version_id, version_no,
checksum, template, documents }` (+ `document_error` string when generation
failed but the snapshot was published). Errors: `400`, `500`.

### POST /packets/{id}/revise
Seeds a fresh draft from the current version. Response `200 { revised }`.
Errors: `400`.

### POST /packets/{id}/revoke | /expire | /archive | /cancel
State transitions (revoke/expire also revoke all buyer tokens).
Response `200 { status: <target> }`. Errors: `400` (illegal transition),
`404`.

### GET /packets/{id}/share
Issues a fresh token (revokes the previous one) and returns
`{ url, token, expires_at }` — the raw token appears exactly once.
Errors: `404`.

### POST /packets/{id}/documents
Regenerate documents for the current version. Optional body `template`.
Response `200 { documents }`. Errors: `400` (no published version / unknown
template), `500`.

### GET /packets/{id}/download?type=pdf|csv|zip&template=…
Merchant download; streams the stored document (permission-checked).
Errors: `400` (no version), `404` (slot missing — regenerate first).

### GET /packets/{id}/events
Response `200 { items: [events] }` (last 200 for this packet).

## Quotes (`src/Api/QuotesController.php`)

### GET /quotes
Query args: `status` (default ''), `page` (1), `per_page` (20).
Response `200 { items: [quote summaries] }`.

### GET /quotes/{id}
Response `200`: `{ quote, lines (with line_total, merchant_note, row_id),
total, report (integrity JSON), changes (vs parent revision: added /
removed / price / qty), packet, version (with checksum), order }`.
Errors: `404`.

### POST /quotes/{id}/review
Quote → `in_review`. Response `200 { quote }`.

### POST /quotes/{id}/approve
Body: optional `notes`. Approves and walks the packet through its legal
transitions. Response `200 { quote }`.

### POST /quotes/{id}/reject
Body: optional `notes`. Response `200 { quote }`; the packet returns to
`issued`.

### POST /quotes/{id}/revise
Body: `changes` (array of line edits: line id + price/qty/note), optional
`notes`. Creates a new revision (parent becomes `superseded`).
Response `200 { quote }` — the returned quote is the NEW revision row.

### POST /quotes/{id}/convert
Converts an ACCEPTED quote into a WooCommerce order at negotiated prices.
Response `201`: `{ order_id, pay_url, … }`; `202 { pending: true, message
}` while a conversion is in flight; replay of an already-converted quote
returns `201 { order_id, already: true, pay_url, message }`.
Errors: `400 tss_validation` (not accepted / already converted but
unreconciled), `500`.

## System (`src/Api/SystemController.php`)

### GET /dashboard
Response `200`: `{ metrics, events: [25 most recent] }`.

### GET /settings
Response `200`: `{ settings (all 15 keys), currency, currency_symbol,
templates: ["trade_table","visual_catalog","technical_sheet"] }`.

### POST /settings
Body: settings map (validated/sanitized centrally). Response `200
{ settings }`.

### GET /diagnostics
Response `200`: `{ checks: [{name,label,ok,value,advice}], schema_version,
plugin_version }`. Check list in
`docs/installation/TROUBLESHOOTING.md`.

## Buyer-side endpoints (NOT REST)

The buyer portal posts to its own URL (`/tradesheet/packet/{REF}/`) with
`tss_action` = `submit_quote` | `upload_csv` | `confirm_csv` |
`accept_quote`, authorized by the share token (bearer) or, for
owner/merchant sessions, the `tss_nonce` nonce. Downloads use
`?download=pdf|csv|zip`. See `docs/product/BUYER_PORTAL.md`.
