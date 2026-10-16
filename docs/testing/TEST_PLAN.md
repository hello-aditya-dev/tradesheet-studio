# Test Plan

TradeSheet Studio uses **bespoke PHP harnesses, not PHPUnit** (the test
environment has no PHPUnit dependency; each suite is a standalone script
with its own assertion helpers and exit code). There are three automated
suites plus a manual HTTP security checklist.

## Environment setup (as executed)

| Component | Value |
|---|---|
| PHP CLI | 8.4.5 (portable static build) at `/home/z/tools/php` (fallback: `php` on PATH) |
| Composer | 2.10.3 at `/home/z/tools/composer.phar` (used for vendoring; not needed at runtime) |
| WordPress | 7.1.3, installed at `/home/z/wp-test/htdocs` |
| WooCommerce | 11.2.1 |
| Database | SQLite via the `sqlite-database-integration` plugin (drop-in `$wpdb` driver) |
| Web server | PHP built-in server with `php -S <host:port> /home/z/wp-test/router.php` |
| WP-CLI | `/home/z/wp-test/dl/wp-cli.phar` |
| Plugins under test | `tradesheet-studio` (symlinked/copied from `plugin/tradesheet-studio`) |
| Verification tools | poppler-utils (`pdfinfo`, `pdftotext`), curl |

Notes: composer platform is pinned to `php 8.1.99` in
`plugin/tradesheet-studio/composer.json` so the vendor bundle stays
PHP-8.1-compatible even though it was installed by PHP 8.4.5. HPOS mode is
toggled in WooCommerce settings; the golden-path suite was executed once
with legacy CPT storage and once with HPOS enabled.

## Suites

### 1. Unit suite — `tests/unit/run.php`

- **How to run:**
  ```bash
  cd /home/z/tradesheet-studio
  /home/z/tools/php tests/unit/run.php        # or: php tests/unit/run.php
  ```
  Exit code 0 = pass. Pure PHP; needs no WordPress, no database, no vendor
  directory (WordPress function stubs are defined in the script).
- **Covers:** decimal money arithmetic (add/sub/mul/percent/rounding,
  half-away-from-zero, bounds), pricing precedence & tie-breaks (the full
  comparator), quantity-break tiers, CSV parse safety including
  formula-injection inertness, canonical JSON serialization, the packet and
  quote state machines, PriceRule validation.

### 2. Integration golden path — `tests/integration/golden-path.php`

- **How to run (inside the WP test install):**
  ```bash
  cd /home/z/wp-test/htdocs
  /home/z/tools/php /home/z/wp-test/dl/wp-cli.phar eval-file \
      /home/z/tradesheet-studio/tests/integration/golden-path.php
  ```
  Requires the plugin active in that WordPress and the demo dataset loaded
  (`fixtures/demo-dataset.php` provides the synthetic "Volt & Wire Trading
  Co." store: products VW-LED-HB150 etc., buyers `krishna.elec` and
  `shakti.ind` — **all data is synthetic**).
- **Covers (11 groups):**
  1. Price resolution through real WooCommerce data (customer-specific
     override 4399.0000; Tier B book-wide 5% → 4939.0500; …)
  2. Packet compose → preflight → publish (immutable snapshot + checksum)
  3. Document generation (real PDF + CSV in private storage)
  4. CSV round-trip with tampered/altered quantities
  5. Buyer token issue / validate / revoke; cross-customer denial
  6. Quote request → integrity report → approve → accept → convert
  7. Idempotency: second conversion returns the SAME order
  8. Price-change discrepancy detection
  9. Merchant revision flow: superseded revision cannot be accepted
  10. Failure cases: deleted product, qty below min, expired offer,
      malformed CSV, formula injection inert, duplicate rows, unknown rows
  11. Privacy export/erase
- **Run it twice** when validating order-storage compatibility: once with
  legacy CPT order storage, once with HPOS enabled.

### 3. PDF smoke test — `tests/pdf-smoke.php`

- **How to run:**
  ```bash
  cd /home/z/tradesheet-studio
  /home/z/tools/php tests/pdf-smoke.php
  ```
  Requires `vendor/autoload.php` (bundled). Renders all three templates
  with synthetic demo data and writes PDFs to a temp directory.
- **Verify manually with poppler:**
  ```bash
  pdfinfo <file>.pdf      # valid structure, page count, page size
  pdftotext <file>.pdf -  # page numbering text, ₹ glyph extraction
  ```
- **Covers:** structural validity of trade_table (landscape A4),
  visual_catalog, technical_sheet; page numbering; Unicode currency glyph
  survival.

### 4. HTTP security suite (curl, live server)

Manual/scripted checks against the running test server — see
`docs/security/SECURITY_TEST_REPORT.md` for the executed list (401s for
unauthenticated REST, nonce enforcement, anti-enumeration 404s, token
authorization, security headers, rate limiting, document streaming,
deactivate/reactivate data preservation).

### 5. Build & lint gates

```bash
cd /home/z/tradesheet-studio
find plugin/tradesheet-studio/src plugin/tradesheet-studio/templates -name '*.php' \
  -exec /home/z/tools/php -l {} \;          # 46/46 pass
cd frontend && bun run typecheck && bun run build   # tsc + esbuild
```

`scripts/build-release.sh` re-runs lint + unit tests as pre-flight gates
before packaging (set `TSS_PHP=/home/z/tools/php` when `php` is not on
PATH).

## Pending (NOT EXECUTED in this environment)

- **1,000-SKU benchmark** — a large-catalog performance run has NOT been
  executed; no timing or memory numbers exist. Do not cite any.
- Multi-version compatibility runs (WP 6.4–7.0, WC 8.0–11.1, PHP
  8.1–8.3): see `COMPATIBILITY_MATRIX.md` — all NOT TESTED.
- Production MySQL/MariaDB execution.

## Result records

Executed results with counts, dates and versions live in
`TEST_RESULTS.md`. Requirements-level tracking lives in
`docs/testing/ACCEPTANCE_LEDGER.md` (maintained alongside this plan).
