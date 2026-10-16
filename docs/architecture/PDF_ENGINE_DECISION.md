# PDF Engine Decision — Dompdf 3.1.6

## Decision

Render DealPacket catalogs with **Dompdf v3.1.6**, vendored into
`plugin/tradesheet-studio/vendor/` at build time via Composer
(`dompdf/dompdf: ^3.1.6`). No external PDF service, no wkhtmltopdf/Chrome
binary, no Imagick requirement.

## Why Dompdf

| Criterion | Assessment |
|---|---|
| Pure PHP | Runs on shared hosting; no binaries, no FFI |
| HTML/CSS templates | Designers can restyle `templates/pdf/*.php` |
| Actively maintained | 3.1.x is the current release line |
| License | LGPL-2.1 (see compatibility below) |
| Security posture | Hardened by default when configured (below) |
| Unicode fonts | Bundled DejaVu covers ₹/€/£ and Devanagari-adjacent needs for our templates |

Alternatives rejected: mPDF (AGPL — GPL-incompatible dual-licensing risk),
TCPDF (API-level layout, weaker CSS), external APIs (data-privacy and
availability), Chrome headless/wkhtmltopdf (hosting portability).

## Security advisories avoided (the reason for ^3.1.6)

A Composer/Packagist advisory audit (2026-10-16) found **6 published
security advisories affecting dompdf < 3.1.6**:

| Advisory | Affects |
|---|---|
| CVE-2026-59943 | dompdf < 3.1.6 |
| CVE-2026-59942 | dompdf < 3.1.6 |
| CVE-2026-59941 | dompdf < 3.1.6 |
| CVE-2026-56722 | dompdf < 3.1.6 |
| CVE-2026-55555 | dompdf < 3.1.6 |
| CVE-2026-55554 | dompdf < 3.1.6 |

**Selecting `^3.1.6` clears all six.** The constraint is pinned in
`plugin/tradesheet-studio/composer.json`; the bundled version (verified in
`vendor/composer/installed.json`) is exactly `v3.1.6`. This decision and the
advisory audit are recorded here and in
`docs/engineering/compatibility-research.md` and
`docs/legal/THIRD_PARTY_LICENSE_AUDIT.md`.

## License compatibility

Product license: **GPL-2.0-or-later** (original code). Runtime bundle:

| Package | Version | License |
|---|---|---|
| dompdf/dompdf | v3.1.6 | LGPL-2.1 |
| dompdf/php-font-lib | 1.0.2 | LGPL-2.1-or-later |
| dompdf/php-svg-lib | 1.0.2 | LGPL-3.0-or-later |
| masterminds/html5 | 2.11.0 | MIT |
| sabberworm/php-css-parser | v9.5.0 | MIT |

MIT is GPL-compatible. LGPL-2.1/3.0 components are **dynamically used via
Composer's autoloader and distributed unmodified in `vendor/`** with their
original LICENSE files and copyright notices intact
(`THIRD-PARTY-NOTICES.md` in the repo root and in the installable ZIP).
LGPL §4 permits redistribution alongside GPL-licensed works when the LGPL
components remain separate works (unmodified, separately identifiable,
with their own license text). No LGPL code is statically modified or
relicensed; the plugin's own GPL code only links to them.

## Fonts

- **DejaVu Sans** (and family) ship inside dompdf's `lib/fonts/` under
  licenses that permit redistribution (the Bitstream Vera derived license —
  see `vendor/dompdf/dompdf/lib/fonts/mustRead.html` and the license audit).
  DejaVu is the `defaultFont`, which is why ₹ and other non-Latin glyphs
  render correctly (verified in the PDF smoke test via pdftotext).
- **Font subsetting is enabled** (`isFontSubsettingEnabled => true`) to
  keep PDF sizes small.
- The merchant logo and product images are embedded as **data URIs** built
  from local attachment files — never fetched from URLs.

## Security posture of the renderer (`src/Documents/PdfRenderer.php`)

| Control | Setting / mechanism |
|---|---|
| Remote resources | `isRemoteEnabled => false` — no SSRF, no network during rendering |
| Untrusted HTML | None reaches Dompdf: product text is `wp_strip_all_tags`'d and re-escaped in the templates; templates render no untrusted HTML |
| Inline PHP | `isPhpEnabled => true` ONLY for the page-numbering scripts that live in the plugin's own templates; the merchant footer line is quote-escaped before interpolation |
| Images | Local media library attachments only, converted to data URIs with size and MIME bounded |
| Time | `set_time_limit(120)` per render |
| Output | Bytes stored in private storage (denied direct web access) and streamed through permission-checked endpoints |

## Templates

| Template | Orientation / paper | Style |
|---|---|---|
| `trade_table` | Landscape A4/Letter | Dense commercial price sheet with page numbering |
| `visual_catalog` | Portrait | Image-led catalog presentation |
| `technical_sheet` | Portrait | Specification sheet with attribute tables |

Paper size follows the `doc_size` setting (A4 default / Letter).

## Verification

`tests/pdf-smoke.php` (executed 2026-10-16) rendered all three templates
with the bundled renderer using the synthetic "Volt & Wire Trading Co."
dataset and verified with poppler (`pdfinfo`/`pdftotext`): 3 structurally
valid PDFs, correct page numbering, and ₹ glyphs surviving text extraction.

## Operations

- The renderer availability is surfaced in Diagnostics
  (`pdf_renderer` check) and the admin bootstrap (`window.TSS_ADMIN.pdfRenderer`).
- A missing/incomplete `vendor/` yields an explicit error ("Reinstall the
  plugin") rather than silent failure.
- Regeneration is idempotent per (version, type, template) slot — the
  unique key `version_doc` in `tss_documents` replaces stale rows and
  files.
