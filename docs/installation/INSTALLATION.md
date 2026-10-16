# Installation Guide

TradeSheet Studio 1.0.0 — WordPress plugin for WooCommerce.
Author: Aditya (<https://dev-aditya.com>) — License: GPL-2.0-or-later.

This guide covers installing from the release ZIP, first-run configuration,
and uninstall behavior. For a guided 10-minute walkthrough see
[QUICK_START.md](QUICK_START.md). For problems see
[TROUBLESHOOTING.md](TROUBLESHOOTING.md).

## 1. Requirements

| Requirement | Minimum | Notes |
|---|---|---|
| WordPress | 6.4 | Plugin header `Requires at least: 6.4` |
| WooCommerce | 8.0 | Must be **installed and active** before activation |
| PHP | 8.1 | 8.2+ recommended; `mbstring` and `dom` extensions required |
| PHP extensions (recommended) | `zip`, `gd` | `zip` enables the bundled ZIP document download; `gd` supports image handling |
| Database | WordPress-compatible MySQL/MariaDB via `$wpdb` | Plugin creates its own 12 `tss_*` tables; WooCommerce tables are never modified |
| Order storage | Legacy CPT **or** HPOS | HPOS compatibility is declared (`custom_order_tables`, true) and tested in both modes |

No Node.js, no Composer, and no paid third-party service is required at
runtime: the PDF renderer (Dompdf 3.1.6) is bundled in `vendor/`, and the
compiled admin app ships in `assets/`.

> Executed-test evidence for specific version combinations is recorded in
> `docs/testing/COMPATIBILITY_MATRIX.md`. Only WordPress 7.1.3 /
> WooCommerce 11.2.1 / PHP 8.4.5 (SQLite test installation) were actually
> executed; all other combinations are NOT TESTED.

## 2. Install from the release ZIP

1. Download `tradesheet-studio-v1.0.0.zip` (the installable artifact; see
   `docs/release/RELEASE_PROCESS.md` for how it is built and verified).
2. In WordPress admin go to **Plugins → Add New → Upload Plugin**.
3. Choose the ZIP file and click **Install Now**.
4. Click **Activate**.

The activation routine (`src/Core/Installer.php`):

- creates the 12 plugin tables via `dbDelta()` (additive, versioned by the
  `tss_schema_version` option),
- creates the private document storage directory
  `uploads/tradesheet-studio-docs/` (web access denied via `.htaccess` +
  `index.php`),
- schedules the hourly `tss_hourly_sweep` maintenance event.

If WooCommerce is not active at activation time, the plugin shows an
admin notice and boots its admin surface only (the
`src/Core/Requirements.php` check runs on every `plugins_loaded`).

## 3. First-run configuration

Open **WooCommerce → TradeSheet Studio** (requires the
`manage_woocommerce` capability — administrators and shop managers).

### 3.1 Company identity (Settings screen)

**Settings** → fill in:

| Field | Purpose |
|---|---|
| Company name / address / email / phone | Printed on PDF catalogs and shown in the buyer portal |
| Logo (media library attachment id) | Embedded in PDFs as a data URI (local file only, never a URL) |
| Document footer line | Quote-escaped into PDF footers |
| Default validity days (default 30) | Offer expiry for new packets |
| Quote validity days (default 14) | Approved-quote expiry |
| Buyer token days (default 30) | Lifetime of buyer portal share links |
| Document size (A4 / Letter) | PDF paper size |
| Default template | `trade_table`, `visual_catalog` or `technical_sheet` |
| File retention days (0 = keep forever) | Generated-document cleanup policy |
| Notify emails | Merchant email notifications for quote events |
| Delete all data on uninstall | **Leave off** unless you want full removal later |

### 3.2 Create a price book

**Price Books** → *New Price Book*: name it, optionally set a validity
window, then add rules (fixed price overrides and/or percent discounts,
minimum quantity, quantity step, quantity-break tiers) and assign
WooCommerce customers to the book. Full model:
`docs/product/PRICE_BOOKS.md`.

### 3.3 Assign customers

**Customers** — search WooCommerce customers and assign each to a price
book (one book per customer). The composer resolves prices from the
assigned book automatically.

## 4. Buyer portal URL format

Each packet gets a reference of the form `TSS-P-XXXX` and a portal URL:

```
https://example.com/tradesheet/packet/TSS-P-AB12/?t=<64-hex-token>
```

- The rewrite rule `^tradesheet/packet/([A-Za-z0-9\-]+)/?$` is registered on
  `init`; permalinks must be non-default (visit **Settings → Permalinks** if
  the route 404s immediately after install).
- `?t=` carries the share token (issued from the packet detail screen).
  Regenerating a share link **revokes the previous token**.
- The buyer may instead log in as the WooCommerce customer the packet
  belongs to; merchants with `manage_woocommerce` always have access.
- Tokens are SHA-256 hashed at rest, expire after *buyer token days*, and
  can be revoked at any time (revoke/expire the packet, or re-share).

Security details for merchants: `docs/product/BUYER_PORTAL.md` and
`docs/security/THREAT_MODEL.md`.

## 5. Deactivation, uninstall, data retention

| Action | Effect |
|---|---|
| **Deactivate** | Nothing is deleted. The hourly sweep is unscheduled; tables, documents, settings and customer assignments remain. |
| **Uninstall (default)** | Data is **preserved** — `uninstall.php` returns early unless you opted in. |
| **Uninstall after enabling "Delete all TradeSheet Studio data on uninstall"** | All 12 tables are dropped, private documents under `uploads/tradesheet-studio-docs/` are deleted, plugin options and the sweep schedule are removed, and the `_tss_pricebook_id` user meta is deleted from every customer. |

Deactivate → reactivate round-trip was executed in the test environment and
verified to preserve data (11 packets before, 11 packets after).

## 6. What gets installed where

```
wp-content/plugins/tradesheet-studio/
├── tradesheet-studio.php        # bootstrap, HPOS declaration
├── uninstall.php                # opt-in data removal
├── src/                         # plugin PHP (autoloaded, no Composer needed at runtime)
├── vendor/                      # bundled Dompdf 3.1.6 + 4 dependencies (LGPL/MIT)
├── assets/                      # compiled admin app (admin.js, admin.css) + buyer.js
├── templates/pdf/               # 3 PDF templates
├── templates/buyer/portal.php   # standalone buyer portal template
└── languages/tradesheet-studio.pot
uploads/tradesheet-studio-docs/  # private generated documents (denied direct access)
```

The database schema is documented in
`docs/architecture/DATABASE_SCHEMA.md`.
