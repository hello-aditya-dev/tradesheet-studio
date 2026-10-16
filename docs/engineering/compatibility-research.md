# Compatibility Research & Engineering Decisions

Research notes with access dates. Sources: official documentation sites.
All decisions recorded with rationale; executed evidence lives in
`docs/testing/`.

## Version support matrix reasoning

Access date for all references below: **2026-10-16**.

### WordPress ≥ 6.4

- Rationale: REST permission callbacks, `wp_is_writable`, modern
  `register_rest_route` argument sanitization, and block-era admin APIs are
  stable from 6.4 onward; 6.4 also predates the oldest version still
  receiving security patches in our support window when development began,
  keeping the matrix honest without dragging legacy code paths.
- References:
  - https://developer.wordpress.org/rest-api/ (REST API handbook)
  - https://developer.wordpress.org/plugins/ (plugin handbook)
- Declared in the plugin header (`Requires at least: 6.4`) and enforced by
  `src/Core/Requirements.php`.
- Executed: WordPress 7.1.3 only (see COMPATIBILITY_MATRIX).

### WooCommerce ≥ 8.0

- Rationale: WooCommerce 8.0 stabilized the HPOS (custom order tables)
  feature declarations API (`FeaturesUtil::declare_compatibility`) and the
  CRUD order interfaces the plugin relies on for order creation; earlier
  lines are past practical support for a new commercial product.
- References:
  - https://developer.woocommerce.com/docs/ (developer docs)
  - HPOS declaration pattern as documented in the WooCommerce dev notes
    (declare on `before_woocommerce_init`)
- Implemented: `tradesheet-studio.php` declares
  `custom_order_tables` compatibility (both declared and TESTED — golden
  path executed with HPOS on and off).
- Plugin header: `WC requires at least: 8.0`, `WC tested up to: 9.9`
  (conservative declaration; the executed environment used WooCommerce
  11.2.1 — the owner may raise the tested-up value at release time).
- Executed: WooCommerce 11.2.1 only.

### PHP ≥ 8.1

- Rationale: enums/readonly/fibers era features and the current security
  support window; `strict_types` everywhere; the codebase uses 8.1-compatible
  syntax only (constructor property promotion, `never`, etc. — no 8.2+
  only features) so the floor is honest.
- Composer platform pinned to `8.1.99` so the vendored bundle stays
  installable/runnable on 8.1.
- Executed: PHP 8.4.5 only (8.1–8.3 interpreters were NOT executed — see
  the compatibility matrix).
- Required extensions: `mbstring`, `dom`; optional: `zip`, `gd`.

### Action Scheduler / WP-Cron

- Background jobs prefer WooCommerce's bundled Action Scheduler
  (`as_schedule_single_action`) when present, with a WP-Cron-based
  fallback (`tss_hourly_sweep`). Reference: https://actionscheduler.org/
  (accessed 2026-10-16). Diagnostics reports both.

## Dompdf advisory analysis (2026-10-16)

- Audit performed through Composer/Packagist advisory data.
- **dompdf < 3.1.6 is affected by 6 published advisories:**
  CVE-2026-59943, CVE-2026-59942, CVE-2026-59941, CVE-2026-56722,
  CVE-2026-55555, CVE-2026-55554.
- **Decision:** require `dompdf/dompdf: ^3.1.6` and vendor exactly v3.1.6 —
  this clears all six advisories. Rationale, license compatibility and the
  renderer's security posture (remote resources disabled, data-URI images,
  inline PHP restricted to our own templates): `docs/architecture/PDF_ENGINE_DECISION.md`.
- Re-audit instruction: run `composer audit` (or check
  https://packagist.org/packages/dompdf/dompdf#advisories) before every
  release; bump within ^3.1 if new advisories appear and record the
  decision here.

## Other recorded engineering decisions

| Decision | Choice | Rationale |
|---|---|---|
| Money arithmetic | Decimal strings, integer-scaled, scale 4 (`src/Support/Money.php`) | No binary floating-point in pricing paths; bounds keep intermediates inside signed 64-bit |
| Percent scale | 2 (`DECIMAL(9,2)` rules column) | Wholesale discount precision convention |
| DB access | `$wpdb` + `dbDelta` only | Portability across MySQL/MariaDB and the SQLite test drop-in; WooCommerce tables never touched |
| Admin React | `window.wp.element` via esbuild, no bundled React | Small bundle (~141 KB), no double-React, WP-native |
| Buyer portal | Standalone template + scoped CSS, form POSTs (not REST) | No theme dependency; token-bearer model; simple audit story |
| Tests | Bespoke harnesses, not PHPUnit | No PHPUnit dependency in the environment; explicit assertion helpers with exit codes |
| State changes | Single-hop state machines; services drive multi-hop milestones | Every hop is validated + audited (see STATE_MACHINES.md §two-step transitions) |
| Token storage | SHA-256 hash only, unique key | Raw token unrecoverable from a DB leak |

## Open items

- 1,000-SKU benchmark NOT EXECUTED (no numbers exist — recorded as pending
  everywhere it matters).
- Consider raising `WC tested up to` after executing a WooCommerce 9.9 run
  (currently only 11.2.1 was executed; header value is a declaration, not
  test evidence).
