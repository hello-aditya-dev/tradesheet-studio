# Architecture

TradeSheet Studio 1.0.0 is a classic-WordPress plugin (no build tooling
needed at runtime) with three surfaces: a REST API, a compiled React admin
app, and a standalone buyer portal template.

```
WordPress
└── WooCommerce
    └── tradesheet-studio.php (bootstrap)
        ├── Core\Plugin (service container, boots everything)
        ├── REST API  tradesheet-studio/v1  ←── Admin app (assets/admin.js)  ←── Buyer portal POSTs
        └── BuyerPortal (rewrite: /tradesheet/packet/{REF}/)
```

## Bootstrap sequence

`tradesheet-studio.php`:

1. Registers the PSR-4-ish autoloader (`src/Support/Autoloader.php`; class
   `TradeSheetStudio\…` → `src/…/….php`).
2. On `before_woocommerce_init` declares HPOS compatibility
   (`custom_order_tables`, true) — required before WooCommerce boots.
3. Activation/deactivation hooks → `Core\Installer` (dbDelta tables,
   private storage dir, options, hourly sweep schedule; deactivation deletes
   nothing).
4. On `plugins_loaded` (priority 20) runs `Core\Requirements`; if
   WordPress/WooCommerce/PHP/extension checks fail it shows an admin notice
   and stops. Otherwise `Core\Plugin` boots: REST routes, admin page, buyer
   portal, privacy exporters/erasers, scheduled sweeps, and the
   `maybeUpgrade()` forward migration on admin requests.

## Service map

All services are lazy singletons resolved through `Core\Plugin`
(`Plugin::instance()->pricing()` etc.).

| Service | File | Responsibility |
|---|---|---|
| ProductAdapter | `src/Products/ProductAdapter.php` | Read-only WooCommerce catalog access: product/variation fetch, prices, stock, supported types (simple + variation), images, tax context (hash + prices-include-tax) |
| PriceBookRepository | `src/Pricing/PriceBookRepository.php` | Books/rules CRUD, customer assignment (user meta `_tss_pricebook_id`), candidate rules, usability window checks |
| PricingEngine | `src/Pricing/PricingEngine.php` | Deterministic single-winner price resolution (pure core `resolveFromData` is WordPress-free and unit-tested); qty-break tiers |
| PriceRule | `src/Pricing/PriceRule.php` | Rule validation/normalization (override XOR percent, min/step, breaks) |
| PacketRepository | `src/Packets/PacketRepository.php` | Packet + line persistence (drafts have `version_id = NULL`), versions, dashboard metrics; all SQL prepared |
| PacketStateMachine | `src/Packets/PacketStateMachine.php` | Packet states and legal transitions (see STATE_MACHINES.md) |
| PacketSnapshotService | `src/Packets/PacketSnapshotService.php` | Preflight + publish (freeze snapshot, SHA-256 checksum, version N+1, supersede previous) + revise (seed new draft) |
| PreflightValidator | `src/Packets/PreflightValidator.php` | Pre-publication findings (blocking errors / warnings) |
| DocumentService | `src/Documents/DocumentService.php` | Generates & stores PDF/CSV/ZIP per version; private storage `uploads/tradesheet-studio-docs/packet-{id}/version-{id}/`; permission-checked streaming with realpath containment |
| PdfRenderer | `src/Documents/PdfRenderer.php` | Dompdf wrapper (remote resources disabled, data-URI images, size caps, font subsetting) |
| CsvOrderSheetService | `src/Documents/CsvOrderSheetService.php` | CSV export/parse/validate (formula-injection safety both directions) |
| BuyerAccessService | `src/Buyers/BuyerAccessService.php` | Token issue/validate/revoke (SHA-256 at rest), share URLs, access mode resolution |
| BuyerPortal | `src/Buyers/BuyerPortal.php` | Rewrite rule + `template_redirect` handler for `/tradesheet/packet/{REF}/`; rate-limited POST actions (submit_quote, upload_csv, confirm_csv, accept_quote); document downloads |
| QuoteRepository | `src/Quotes/QuoteRepository.php` | Quote persistence + lifecycle (mirrors packet transitions), one active chain per packet, expiry sweep |
| QuoteIntegrityEngine | `src/Quotes/QuoteIntegrityEngine.php` | Snapshot-vs-live-store discrepancy report (the product's centerpiece) |
| QuoteRevisionService | `src/Quotes/QuoteRevisionService.php` | Merchant revisions: new revision row chained via `parent_quote_id`, supersede parent |
| WooCommerceOrderBridge | `src/Orders/WooCommerceOrderBridge.php` | Idempotent quote→order conversion at negotiated prices; order links; stale-conversion recovery |
| JobCoordinator | `src/Jobs/JobCoordinator.php` | Background jobs in `tss_jobs` (queued/running/done/failed, attempts, checkpoint), Action Scheduler when available with WP-Cron fallback |
| EventLogger | `src/Support/EventLogger.php` | Append-only audit trail into `tss_events` |
| PrivacyService | `src/Privacy/PrivacyService.php` | WordPress personal-data export/eraser integration (tokens + buyer notes erased; financial records retained with an explicit note) |
| MerchantSettingsService | `src/Settings/MerchantSettingsService.php` | Central settings option (`tss_settings`) with sanitization and graceful defaults |
| Money | `src/Support/Money.php` | Scale-4 decimal-string arithmetic, integer-scaled; percent scale 2; `fromDb` canonicalization |
| Capabilities / RateLimiter | `src/Security/` | `manage_woocommerce` capability (filterable via `tss_manage_capability`); transient fixed-window limiter |

> Note: **customer search** (for the composer and Customers screen) lives in
> `src/Api/CatalogController.php` (`GET /customers`), not in a customer
> repository — customer data is read directly through `WP_User_Query` with
> the price-book assignment decorated per row.

## REST layer

Namespace `tradesheet-studio/v1`, registered by `src/Api/Routes.php` through
five controllers — Catalog, PriceBooks, Packets, Quotes, System. Every route
declares a `permission_callback`:

- merchant routes → `Capabilities::restCanManage`
  (`manage_woocommerce`, filterable);
- cookie-authenticated state-changing requests additionally require a valid
  `X-WP-Nonce`, enforced by the WordPress REST infrastructure.

Full endpoint reference: `API_REFERENCE.md`. The buyer portal does NOT use
REST — it posts form actions to its own URL with token-or-nonce
authorization (see `docs/product/BUYER_PORTAL.md`).

## Admin app build

- Source: `frontend/` (TypeScript, strict). Views: Overview, Packets,
  PacketDetail, PacketCreate, PriceBooks, Quotes, Customers, Settings,
  Diagnostics, Help; a scoped `admin.css` (~1500 lines, 13px base, teal
  `#0f766e` accent, amber `#b45309` highlights).
- Build: **esbuild** (`frontend/esbuild.mjs`) with
  `jsxFactory: wp.element.createElement` /
  `jsxFragment: wp.element.Fragment` — the emitted bundle references the
  WordPress global directly. **No React is bundled**: devDependencies are
  only esbuild + TypeScript; types for the `wp` subset are hand-written
  (`frontend/src/types/wp.d.ts`).
- Runtime mount: `src/Admin/AdminPage.php` enqueues
  `assets/admin.js` (deps `wp-element`, `wp-i18n`, `wp-api-fetch`,
  `wp-components`) + `assets/admin.css` only on
  `woocommerce_page_tradesheet-studio`, and injects the `window.TSS_ADMIN`
  bootstrap (REST URL, `wp_rest` nonce, versions, currency, pdf-renderer
  availability). The app boots into `#tss-admin-root` with a WP-style error
  notice fallback when `wp.element` is missing.
- `bun run typecheck` (tsc --noEmit) and `bun run build` are the verified
  dev commands (npm works too).

## Buyer portal

- Standalone template `templates/buyer/portal.php` + `assets/buyer.js`,
  scoped CSS — no theme dependencies, no admin styles.
- Rewrite `^tradesheet/packet/([A-Za-z0-9\-]+)/?$` → query var
  `tss_packet_ref`, handled on `template_redirect`.
- Responses: `nocache_headers()`, `X-Robots-Tag: noindex, nofollow`,
  `Referrer-Policy: no-referrer`. Denials are uniform 404-style pages.

## Data flow — publish

```
draft lines ──publish──► tss_packet_versions (snapshot JSON + SHA-256 checksum, version_no N)
                          ├─► tss_packet_lines (copied, version_id set)      [immutable]
                          ├─► DocumentService ─► PDF (Dompdf) / CSV / ZIP ──► private storage + tss_documents
                          ├─► packet.status: draft → issued
                          └─► tss_events audit row
```

## Data flow — accept & convert

```
buyer Accept (portal) ─► QuoteRepository::accept ─► quote accepted; packet → accepted
                      └► WooCommerceOrderBridge::convert
                          ├─ atomic claim: tss_order_links (PK quote_id, UNIQUE conversion_key)
                          ├─ WC order CRUD at negotiated line prices; quote ref in order meta
                          ├─ quote → ordered; packet → ordered
                          └─ replays return the same order / 202 pending / sweep reconciles
```

## Non-goals / deliberate constraints (v1)

- No multicurrency (packet currency must equal the store currency).
- Product support: simple products and specific variations only.
- One active quote chain per packet.
- No checkout modification: order creation is programmatic (admin/portal),
  payment happens through normal WooCommerce flows.
