# Test Results — executed evidence

All results below were **actually executed on 2026-10-16** in the sandbox
test environment. No result is inferred or projected. Anything not listed
here was NOT TESTED (see `COMPATIBILITY_MATRIX.md` and
`docs/security/SECURITY_TEST_REPORT.md`).

## Environment

| Component | Version |
|---|---|
| WordPress | 7.1.3 |
| WooCommerce | 11.2.1 |
| PHP | 8.4.5 (portable static CLI; `dom`, `mbstring`, `sqlite3`, `gd`, `zip`, `bcmath`, `curl`, `fileinfo`) |
| Database | SQLite via sqlite-database-integration drop-in |
| Order storage | Run A: legacy CPT posts · Run B: HPOS enabled |
| Test runner | bespoke PHP harnesses (PHPUnit NOT used); wp-cli.phar for integration; curl for HTTP |
| Plugin | TradeSheet Studio 1.0.0 (schema `2026-10-16-1`) |
| Demo data | fixtures/demo-dataset.php — "Volt & Wire Trading Co." + buyers Krishna Electricals / Shakti Industries — **100% synthetic** |

## Suite results

| Suite | Command | Result |
|---|---|---|
| Unit | `/home/z/tools/php tests/unit/run.php` | **PASS — 105 assertions, 0 failures** |
| Integration (Run A — legacy CPT order storage) | `wp eval-file tests/integration/golden-path.php` | **PASS — 78 assertions, 0 failures** |
| Integration (Run B — HPOS enabled) | same, after enabling HPOS | **PASS — 78 assertions, 0 failures** |
| PDF smoke | `/home/z/tools/php tests/pdf-smoke.php` | **PASS — 3 valid PDFs** (poppler-verified) |
| PHP lint | `php -l` over 46 plugin files | **PASS — 46/46** |
| HTTP security suite | curl vs live built-in server | **PASS — 14/14 checks** (list in SECURITY_TEST_REPORT.md) |

### Unit suite coverage detail (105 assertions)

- Money: validity, canonicalization, add/sub/mul/percent, half-away-from-zero
  rounding, bounds, `fromDb` driver normalization.
- Pricing: precedence order, scope ranking, priority and lowest-id
  tie-breaks, percent computation, qty-break tier replacement.
- CSV: header detection, meta-row skipping, row caps, error codes,
  **formula-injection inertness** (export neutralization + import
  rejection of non-integer quantities).
- Canonical JSON serialization; packet state machine transitions
  (legal/illegal); quote state machine transitions; PriceRule validation
  (override XOR percent, min/step, breaks).

### Integration golden path (78 assertions per run)

Covered groups (11): pricing through WooCommerce data (customer-specific
override `4399.0000`; book-wide 5% → `4939.0500`), compose → preflight →
publish (snapshot + SHA-256 checksum), real document generation (PDF + CSV
in private storage), CSV round-trip incl. tampering, buyer token
issue/validate/revoke + cross-customer denial, quote request → integrity
report → approve → accept → convert, **idempotent second conversion
(returns the SAME order — order #94 verified at the negotiated price)**,
price-change discrepancy detection, merchant revision flow (superseded
revision cannot be accepted), failure cases (deleted product, qty below
min, expired offer, malformed CSV, formula injection inert, duplicate
rows, unknown rows), privacy export/erase.

### HPOS note

Run B was executed with High-Performance Order Storage enabled; the
plugin's declared compatibility (`custom_order_tables`, true) was thereby
exercised end-to-end, including order creation and idempotency.

### PDF smoke detail

Three PDFs rendered with the bundled Dompdf 3.1.6 + plugin templates:

| Template | Paper | Verification |
|---|---|---|
| trade_table | Landscape A4 | pdfinfo valid; page numbering present; ₹ extracted by pdftotext |
| visual_catalog | Portrait A4 | pdfinfo valid |
| technical_sheet | Portrait A4 | pdfinfo valid |

## Honest gaps (NOT executed — do not cite as working)

- Only WP 7.1.3 / WC 11.2.1 / PHP 8.4.5 were ever executed. No runs on
  WordPress 6.4–7.0, WooCommerce 8.0–11.1, or PHP 8.1–8.3 (composer platform
  is pinned to 8.1.99, but those runtimes were not executed).
- Production MySQL/MariaDB not exercised (SQLite drop-in used; all DB
  access is via `$wpdb`).
- Block vs classic checkout, third-party themes, payment gateway flows —
  not exercised.
- 1,000-SKU benchmark — NOT EXECUTED; no performance numbers exist.
- Real multi-user browser sessions beyond curl; no penetration test.

See `docs/testing/COMPATIBILITY_MATRIX.md` for the full version matrix and
`docs/security/SECURITY_TEST_REPORT.md` for the security-specific detail.
