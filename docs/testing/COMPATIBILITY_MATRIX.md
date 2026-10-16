# Compatibility Matrix

Legend: **PASS** = executed test evidence exists (2026-10-16, sandbox
environment). **NOT TESTED** = never executed in any environment — no
claim of compatibility is made beyond the plugin's declared requirements.

Declared requirements (plugin header): WordPress ≥ 6.4, WooCommerce ≥ 8.0
(header field `WC tested up to: 9.9` — a conservative declaration; the
executed environment used WooCommerce 11.2.1), PHP ≥ 8.1 with `mbstring`
and `dom`.

## Core stack

| Component | Version | Status | Evidence / Reason |
|---|---|---|---|
| WordPress | 7.1.3 | **PASS** | Integration golden path ×2 (CPT + HPOS), 78 assertions each; HTTP security suite; admin mount verified |
| WordPress | 6.4 – 7.0 | **NOT TESTED** | No installation of these versions was available in the environment; only WP 7.1.3 was executed |
| WooCommerce | 11.2.1 | **PASS** | Same integration runs; order #94 created at negotiated price; HPOS run included |
| WooCommerce | 8.0 – 11.1 | **NOT TESTED** | Only WC 11.2.1 was installed; no other WooCommerce version was downloaded/installed for testing |
| PHP | 8.4.5 | **PASS** | All suites executed with the portable PHP 8.4.5 CLI |
| PHP | 8.1 – 8.3 | **NOT TESTED** | Runtimes not available in the environment. Composer platform is pinned to `8.1.99` (vendor bundle built for ≥8.1 compatibility), but no 8.1–8.3 interpreter was ever run |
| Database: SQLite (drop-in) | sqlite-database-integration | **PASS** | The executed environment itself |
| Database: MySQL / MariaDB (production) | any | **NOT TESTED** | All access is via `$wpdb` and `Money::fromDb` canonicalizes driver differences, but production MySQL/MariaDB was never executed in this environment |

## Order storage (HPOS)

| Mode | Status | Evidence |
|---|---|---|
| Legacy CPT order storage | **PASS** | Golden path Run A: 78/78 assertions |
| HPOS (custom order tables) enabled | **PASS** | Golden path Run B: 78/78 assertions; compatibility declared at boot (`before_woocommerce_init`) |

## Frontend / rendering surfaces

| Surface | Status | Evidence / Reason |
|---|---|---|
| Admin app on current WP admin (classic) | **PASS** | Admin page mount + asset enqueue verified over HTTP |
| Block editor / block-based admin contexts | **NOT TESTED** | Not exercised; the plugin registers a classic submenu page only |
| Buyer portal on default (unstyled) theme context | **PASS** | Portal is a standalone template with scoped CSS (no theme dependency exercised); rendered and verified over HTTP |
| Third-party themes | **NOT TESTED** | Portal does not use theme templates, but no third-party theme was installed to confirm no interference |
| Block checkout | **NOT TESTED** | Plugin creates orders programmatically; checkout flows were not exercised |
| Classic checkout | **NOT TESTED** | Same — no checkout flow was driven end-to-end |
| Payment gateways (any) | **NOT TESTED** | Order `pay_url` surfaced but no gateway was exercised |

## Server environment

| Surface | Status | Reason |
|---|---|---|
| Apache + `.htaccess` document deny | **NOT TESTED** | Built-in PHP server used; the `.htaccess` was not exercised (streaming-only delivery is the primary control) |
| nginx / LiteSpeed / IIS | **NOT TESTED** | Not available |
| Multisite | **NOT TESTED** | Not declared, not tested |
| Object cache (Redis/Memcached) for rate-limit transients | **NOT TESTED** | Transient-based limiter not exercised against external object caches |

## Extensions

| Extension | Required? | Status |
|---|---|---|
| mbstring | **Required** | **PASS** (loaded in executed env; required by Requirements check) |
| dom | **Required** | **PASS** |
| zip | Optional (ZIP bundles) | **PASS** (loaded; ZIP bundles generated) |
| gd | Optional (images) | **PASS** (loaded) |

## Scale

| Scenario | Status | Reason |
|---|---|---|
| 1,000-SKU packet benchmark | **NOT TESTED / PENDING** | Not executed in this environment; no performance numbers exist — do not cite any |

## Notes

- The compatibility reasoning (why WP ≥ 6.4 / WC ≥ 8.0 / PHP ≥ 8.1 were
  chosen, with official documentation links) is recorded in
  `docs/engineering/compatibility-research.md`.
- Executed test detail: `docs/testing/TEST_RESULTS.md`; environment setup:
  `docs/testing/TEST_PLAN.md`.
