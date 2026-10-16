# Troubleshooting

Read this together with the built-in **Diagnostics** screen
(**WooCommerce → TradeSheet Studio → Diagnostics**), which reports live,
real environment checks (never fabricated results) plus the plugin and
schema versions. When contacting support, paste the Diagnostics output —
see `docs/support/SUPPORT.md`.

## Requirements notice at activation

**Symptom:** after activation an admin notice says WooCommerce is missing,
or the plugin does not appear under the WooCommerce menu.

**Meaning:** `src/Core/Requirements.php` runs on every `plugins_loaded` and
requires WordPress ≥ 6.4, WooCommerce ≥ 8.0 active, PHP ≥ 8.1 with the
`mbstring` and `dom` extensions. The tables are created at activation, but
the REST routes, admin app, buyer portal and cron sweeps only boot once the
requirements pass.

**Fix:** install/activate WooCommerce first; check **Site Health → Info →
Server** for PHP version and extensions. If the notice persists after
fixing, deactivate/reactivate once so the activation routine completes.

## Missing or broken PDF renderer

**Symptom:** publishing or regenerating documents fails with
"The bundled PDF renderer is missing. Reinstall the plugin." — or the
Diagnostics check *PDF renderer* shows `missing`.

**Meaning:** the bundled Dompdf package under
`wp-content/plugins/tradesheet-studio/vendor/` is incomplete (partial FTP
upload or a corrupted ZIP).

**Fix:** delete the plugin directory and re-upload the release ZIP. The
runtime never needs Composer — `vendor/` ships complete in the artifact.
Also confirm the `dom` and `mbstring` PHP extensions (Diagnostics).

## Missing compiled admin assets

**Symptom:** the admin page mounts an empty shell or shows a notice about
missing compiled assets instead of the app.

**Meaning:** `assets/admin.js` / `assets/admin.css` are absent. The admin
screen (`src/Admin/AdminPage.php`) refuses to enqueue a script that does not
exist on disk.

**Fix:** reinstall from the official release ZIP (compiled assets are
committed to the artifact). If you built from source, run
`cd frontend && npm install && npm run build` (or with `bun`) to regenerate
`plugin/tradesheet-studio/assets/admin.js` and `admin.css`.

## Buyer portal returns 404

- **Permalinks:** the route `/tradesheet/packet/{REF}/` is a rewrite rule
  registered on `init`. If your site uses the default ("Plain") permalink
  structure, visit **Settings → Permalinks** and save once to flush rules.
- **Unknown or revoked access:** a missing packet, a bad/expired/revoked
  token, and a non-owner visitor all deliberately produce the same generic
  404-style denial (anti-enumeration). Re-issue the share link from the
  packet detail screen if the token expired.
- **Packet not live:** the portal only serves packets in the live states
  (`issued`, `submitted`, `in_review`, `approved`, `accepted`). Revoked,
  expired, cancelled and archived packets deny access.

## WP-Cron disabled / scheduled tasks not running

**Diagnostics check:** *Task scheduling health* reports
`Hourly sweep scheduled/missing; WP-Cron enabled/disabled`.

**Meaning:** TradeSheet Studio schedules `tss_hourly_sweep` (quote expiry,
token cleanup, stale-conversion recovery). If `DISABLE_WP_CRON` is true and
nothing triggers `wp-cron.php`, the sweep stops.

**Fix:** trigger wp-cron.php from a system cron, or rely on Action
Scheduler (the sweep also runs through WooCommerce's Action Scheduler when
available — the Diagnostics *Action Scheduler* check shows whether
`as_schedule_single_action` exists). The admin boot also reconciles pending
work on admin requests.

## Uploads directory not writable

**Symptom:** publishing succeeds but document generation fails with
"The document could not be written to private storage."

**Meaning:** `uploads/tradesheet-studio-docs/` (created at activation) is
not writable by the web server, or free disk space is below the ~100 MB
headroom the Diagnostics check expects for large PDFs.

**Fix:** fix ownership/permissions (`wp_is_writable` is checked in
Diagnostics), free disk space, then **Regenerate documents** on the packet.

## HPOS notes

The plugin declares WooCommerce custom-order-table (HPOS) compatibility at
boot (`before_woocommerce_init`) and works with **both** legacy CPT order
storage and HPOS. The golden-path integration suite was executed twice —
once with each mode — with identical results (78 assertions passed in each
run). Nothing needs configuring; the Diagnostics *High-Performance Order
Storage* row is informational.

If you switch a live store between order storages, WooCommerce itself
migrates order data; TradeSheet Studio only stores order ids in its
`tss_order_links` table and reads orders through the WooCommerce CRUD, so
no plugin-side migration is involved.

## Diagnostics screen pointer

The Diagnostics screen reports these checks with `ok/value/advice` fields
(served by `GET /tradesheet-studio/v1/diagnostics`):

| Check | What it verifies |
|---|---|
| `php_version` | PHP ≥ 8.1 |
| `wordpress_version` | WordPress ≥ 6.4 |
| `woocommerce` | WooCommerce ≥ 8.0 active |
| `hpos` | Order storage mode (informational) |
| `action_scheduler` | `as_schedule_single_action` availability |
| `task_scheduling` | Hourly sweep scheduled; cron health |
| `document_storage` | Private document dir exists and is writable |
| `pdf_renderer` | Bundled Dompdf present |
| `ext_mbstring` / `ext_dom` / `ext_zip` / `ext_gd` | PHP extensions |
| `disk_space` | Free space above ~100 MB |
| `failed_jobs` | Count of failed background jobs |

## Still stuck?

Collect: (1) the Diagnostics output, (2) the plugin version (1.0.0) and
schema version, (3) the packet/quote reference (`TSS-P-XXXX` / `TSS-Q-XXXX`)
and (4) the relevant entry from the packet's **Events** timeline, then
contact support as described in `docs/support/SUPPORT.md`.
