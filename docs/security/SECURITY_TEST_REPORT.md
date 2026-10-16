# Security Test Report

**Date executed:** 2026-10-16 · **Product:** TradeSheet Studio 1.0.0
**Environment:** WordPress 7.1.3, WooCommerce 11.2.1, PHP 8.4.5, SQLite via
the sqlite-database-integration drop-in, PHP built-in web server + router,
tests driven with curl against the live server.

This report lists only tests that were **actually executed**. Anything not
listed below is NOT TESTED — see the final section.

## Executed HTTP security suite (curl against the live server)

| # | Test | Result |
|---|---|---|
| 1 | Unauthenticated REST request (`GET /wp-json/tradesheet-studio/v1/…`) | **PASS** — HTTP 401 |
| 2 | Cookie-authenticated REST without nonce | **PASS** — 401/403 (REST infrastructure) |
| 3 | Authenticated POST without nonce | **PASS** — 401 |
| 4 | Portal: unknown packet reference | **PASS** — generic 404 (anti-enumeration) |
| 5 | Portal: invalid token on a real packet | **PASS** — 404 (identical to unknown-packet response) |
| 6 | Portal: valid token | **PASS** — HTTP 200, offer content served |
| 7 | Portal response headers: `X-Robots-Tag: noindex, nofollow` | **PASS** — present |
| 8 | Portal response headers: no-cache headers | **PASS** — present (`nocache_headers()`) |
| 9 | Portal response headers: `Referrer-Policy: no-referrer` | **PASS** — present |
| 10 | PDF download via token (`?download=pdf`) | **PASS** — streamed with authorization |
| 11 | CSV download via token (`?download=csv`) | **PASS** — streamed with authorization |
| 12 | Rate limiting on portal POST actions | **PASS** — limits observed (submit 10/h, upload 10/h, accept 20/h) |
| 13 | Admin page mount + assets | **PASS** — page mounts; `admin.js`/`admin.css` enqueue on the plugin's hook only |
| 14 | Deactivate → reactivate preserves data | **PASS** — 11 packets before, 11 packets after |

## Supporting executed evidence

| Suite | Result | Evidence |
|---|---|---|
| Unit suite (`tests/unit/run.php`) | **105/105 assertions PASS** | money arithmetic (incl. percent/rounding), pricing precedence & tie-breaks, qty breaks, CSV parse safety incl. formula-injection inertness, canonical JSON, packet & quote state machines, PriceRule validation |
| Integration golden path (`tests/integration/golden-path.php`), **run twice** (legacy CPT order storage, then HPOS enabled) | **78/78 assertions PASS in each run** | order #94 created at the negotiated price; idempotent conversion under BOTH storage modes |
| PDF smoke (`tests/pdf-smoke.php`) | **3/3 valid PDFs** (poppler pdfinfo/pdftotext) | trade_table (landscape A4), visual_catalog, technical_sheet; page numbering; ₹ Unicode glyph extraction |
| PHP lint | **46/46 files pass** | `php -l` over all plugin PHP files |

Security-relevant behaviors covered *inside* the integration suite:
formula-injection inertness, tampered CSV rejection, cross-customer token
denial, token revoke, deleted-product blocking finding, quantity-rule
violations, expired-offer refusal, superseded-revision acceptance refusal.

## NOT TESTED (honest list)

The following were **not exercised** in this environment and must not be
claimed as working:

- **Real multi-user browser sessions** beyond curl (no Selenium/Playwright
  browser matrix, no cross-browser session testing).
- **Penetration-test depth** — no professional pentest, no fuzzing
  campaigns, no authenticated vulnerability scanning was performed.
- Production **MySQL/MariaDB** (the test environment used the SQLite
  drop-in; all DB access is via `$wpdb`, but driver-specific behaviors of
  production MySQL were not executed).
- Older **WordPress/WooCommerce/PHP versions** (only WP 7.1.3 / WC 11.2.1 /
  PHP 8.4.5 executed) — see `docs/testing/COMPATIBILITY_MATRIX.md`.
- **Block vs classic checkout, third-party themes, payment gateway flows**
  — not exercised (the plugin creates orders programmatically and does not
  modify checkout, but no gateway was driven end-to-end).
- **nginx / LiteSpeed / IIS** web servers (the `.htaccess` deny was not
  verified outside Apache-style config; permission-checked streaming is the
  primary document control).
- Object-cache-backed rate limiter behavior (transients on Redis/Memcached).
- Email delivery (notifications depend on the host's mail setup).
- **1,000-SKU benchmark** — NOT EXECUTED in this environment; no
  performance numbers exist yet.

## Reporting vulnerabilities

See `SECURITY.md` in the repository root (supported version, contact
`hi.aditya.dev@gmail.com`, response expectations, scope).
