# Threat Model (STRIDE-style)

Scope: TradeSheet Studio 1.0.0 as installed on a WooCommerce store. The
model below was written first and then implemented — controls reference the
files that implement them. Executed verification is recorded in
`SECURITY_TEST_REPORT.md`.

## Assets

| # | Asset | Why it matters |
|---|---|---|
| A1 | Buyer-specific wholesale prices (price books, packet snapshots, negotiated quote prices) | Commercially sensitive; the product's core value |
| A2 | Buyer portal share tokens | Grant access to A1 without login |
| A3 | CSV uploads (untrusted buyer files) | Attack surface into the merchant's browser (spreadsheet formulas) and the server (parser abuse) |
| A4 | PDF rendering pipeline (HTML → Dompdf) | Historically SSRF/RCE-prone in PDF libraries |
| A5 | Quote→order conversion | Must not double-charge / duplicate orders |
| A6 | Generated documents in private storage | Must not be publicly linkable |
| A7 | Audit trail (tss_events) and snapshot integrity | Commercial non-repudiation |
| A8 | Merchant settings / company identity | Printed onto buyer-facing documents |

## Spoofing

| Threat | Control | Where |
|---|---|---|
| Forged buyer identity on the portal (S-A2) | Tokens are 64-hex from `random_bytes(32)`; only SHA-256 hashes stored; validation is hash lookup + revoked/expired checks; brute force is impractical and rate limited | `src/Buyers/BuyerAccessService.php`, `src/Support/Utils.php` |
| CSRF against portal POSTs by cookie-authenticated owner/merchant (S-A1/A5) | WordPress nonce (`tss_nonce`, action `tss_buyer_{packetId}`) required for owner/merchant mode; token mode uses possession-as-bearer | `src/Buyers/BuyerPortal.php` |
| CSRF against the REST API from a merchant browser | REST routes declare permission callbacks; WP REST enforces `X-WP-Nonce` for cookie-authenticated state changes | `src/Api/Routes.php`, `src/Security/Capabilities.php` |
| Impersonating the merchant on REST | `manage_woocommerce` capability required on every route (filterable) | `src/Security/Capabilities.php` |

## Tampering

| Threat | Control | Where |
|---|---|---|
| Buyer tampers with prices via the portal or CSV (T-A1) | Quantities are the ONLY buyer input honored; UnitPrice column informational; all quote prices taken from the immutable snapshot or explicit merchant revision | `src/Quotes/QuoteRepository.php`, `src/Documents/CsvOrderSheetService.php` |
| Row substitution / invention in CSV uploads (T-A1) | RowID must exist in the quoted packet version; duplicates and unknown rows rejected; SKU/name mismatches produce warnings only | `src/Documents/CsvOrderSheetService.php` |
| Rewriting an issued offer (T-A7) | Issued versions immutable; edits require a new version (checksum, `superseded`); draft-only mutation enforced in controller and repository | `src/Packets/PacketRepository.php`, `src/Api/PacketsController.php` |
| SQL injection into packet/quote/token queries (T-A*) | All SQL via `$wpdb->prepare()`; table names from a fixed internal list | all repositories |
| Path traversal through stored document paths (T-A6) | Relative paths resolved with `realpath()` and containment check against the storage root | `src/Documents/DocumentService.php` |
| Formula injection into the merchant's spreadsheet (T-A3) | Export neutralizes leading `= + - @ TAB CR` with a `'` prefix; import parses with `fgetcsv` only (no interpretation) and qty must match `^\d{1,6}$` | `src/Support/Utils.php::csvSafe`, `src/Documents/CsvOrderSheetService.php` |
| XSS in admin/portal rendering | Server-side escaping in templates; admin app renders API data as text; PDF templates escape dynamic output | templates, frontend views |

## Repudiation

| Threat | Control | Where |
|---|---|---|
| Merchant/buyer denies a commercial action (R-A7) | Append-only `tss_events` audit trail with actor, action, details, timestamp for every state change, share, rule save, conversion | `src/Support/EventLogger.php` |
| Dispute over what was offered | Immutable version snapshots + SHA-256 content checksum; every document prints issue date, version, validity window | `src/Packets/PacketSnapshotService.php`, `src/Documents/DocumentService.php` |

## Information disclosure

| Threat | Control | Where |
|---|---|---|
| Packet enumeration on the portal (I-A1/A2) | Missing packet, bad token, expired token and wrong visitor all return the same generic 404-style denial | `src/Buyers/BuyerPortal.php` |
| Token leakage from the database (I-A2) | Only SHA-256 hashes stored (unique key); raw token returned exactly once at issue time | `src/Buyers/BuyerAccessService.php` |
| Portal content cached/indexed/leaked via referrer (I-A1) | `nocache_headers()`, `X-Robots-Tag: noindex, nofollow`, `Referrer-Policy: no-referrer` on every portal response | `src/Buyers/BuyerPortal.php` |
| Direct web access to generated documents (I-A6) | Private storage `uploads/tradesheet-studio-docs/` with `.htaccess` `Require all denied` + `index.php`; streaming only through permission-checked endpoints (defence in depth) | `src/Core/Installer.php`, `src/Documents/DocumentService.php` |
| SSRF / remote-file inclusion during PDF rendering (I/A4) | Dompdf `isRemoteEnabled => false`; images/logo embedded as data URIs from local attachments with size/MIME bounds | `src/Documents/PdfRenderer.php` |
| Unauthenticated REST access to pricing (I-A1) | All REST routes require the manage capability (verified: unauthenticated REST → 401) | `src/Api/Routes.php` |

## Denial of service

| Threat | Control | Where |
|---|---|---|
| Quote-submission flooding from a token (D-A5) | Fixed-window rate limiter: submit 10/hour, upload 10/hour, accept 20/hour per token hash or user id | `src/Security/RateLimiter.php`, `src/Buyers/BuyerPortal.php` |
| Oversized CSV bombs (D-A3) | Hard size bounds 12 bytes–2 MiB; 2000-row cap; `fgetcsv` line-length cap 4096 | `src/Buyers/BuyerPortal.php`, `src/Documents/CsvOrderSheetService.php` |
| PDF render exhaustion (D-A4) | `set_time_limit(120)` per render; size-bounded images; subsetting | `src/Documents/PdfRenderer.php` |
| Infinite retry loops in background jobs (D-*) | `tss_jobs.max_attempts` (default 3) + checkpoint column; failed jobs surfaced in Diagnostics | `src/Jobs/JobCoordinator.php` |

## Elevation of privilege

| Threat | Control | Where |
|---|---|---|
| Buyer escalates to merchant functions (E-A*) | Portal actions are an allow-list (`submit_quote`, `upload_csv`, `confirm_csv`, `accept_quote`) — no merchant capability is reachable through it; REST requires the manage capability | `src/Buyers/BuyerPortal.php`, `src/Api/Routes.php` |
| Settings write by low-priv users (E-A8) | `POST /settings` gated on the same capability; settings sanitized centrally | `src/Settings/MerchantSettingsService.php` |
| Uninstall-time data destruction as sabotage (E-A6/A7) | Data deletion requires the explicit opt-in setting; deactivation deletes nothing | `uninstall.php`, `src/Core/Installer.php` |

## A5-specific: conversion integrity (double-spend)

| Threat | Control | Where |
|---|---|---|
| Concurrent/duplicate acceptance creates two orders (T/E-A5) | `tss_order_links` PRIMARY KEY on quote_id + UNIQUE conversion_key = atomic claim; replays return the same order; in-flight conversions answer `202 pending`; stale claims (10 min) reconciled by searching WooCommerce for the order carrying the quote-reference meta before retry | `src/Orders/WooCommerceOrderBridge.php` |

## Residual risks (documented honestly)

- Rate limiting uses transients — an object-cache-backed site gets
  site-wide buckets, and the limiter is per-IP (behind proxies, REMOTE_ADDR
  may be the proxy).
- Token URLs in email/chat can leak (inherent to link sharing); mitigate by
  short `buyer_token_days`, re-share (revokes), packet revoke.
- The `.htaccess` deny relies on Apache (nginx needs an equivalent rule);
  streaming-only delivery is the primary control, so this is defence in
  depth, not the only barrier.
- See `SECURITY_TEST_REPORT.md` for what was actually executed vs NOT
  TESTED (e.g., no full penetration test was performed).
