# Third-Party License Audit (runtime SBOM)

Audit date: 2026-10-16. Sources: `plugin/tradesheet-studio/composer.json`,
`plugin/tradesheet-studio/composer.lock`,
`plugin/tradesheet-studio/vendor/composer/installed.json` (versions and
license metadata verified from the lock/installed data), plus each
package's LICENSE file shipped in `vendor/`. Advisory status via
Composer/Packagist audit.

Distribution model: the installable ZIP (`tradesheet-studio-v1.0.0.zip`)
ships the plugin's GPL-2.0-or-later code **together with the following
unmodified packages** in `vendor/`, each retaining its own LICENSE file and
notices. See `THIRD-PARTY-NOTICES.md` (repo root; also copied into the ZIP)
for the attribution blocks.

## Runtime dependencies (distributed in the installable ZIP)

| Package | Version | Source URL | License | Copyright / notice | Redistribution obligation | Modification status | Security advisories | Inclusion decision |
|---|---|---|---|---|---|---|---|---|
| dompdf/dompdf | v3.1.6 | https://packagist.org/packages/dompdf/dompdf | LGPL-2.1 | © dompdf community; AUTHORS.md + LICENSE.LGPL in package | LGPL §4: keep license + notices, state changes (none), keep as separate work when combined | **Unmodified** (vendored as released) | 6 advisories affect < 3.1.6 (CVE-2026-59943, CVE-2026-59942, CVE-2026-59941, CVE-2026-56722, CVE-2026-55555, CVE-2026-55554) — **all cleared by v3.1.6** | INCLUDE — core PDF renderer; advisory-clean version pinned (`^3.1.6`) |
| dompdf/php-font-lib | 1.0.2 | https://packagist.org/packages/dompdf/php-font-lib | LGPL-2.1-or-later | © Brent Bryan / dompdf; LICENSE in package | Same LGPL notice obligations | **Unmodified** | No open advisories at this version (transitive dep of dompdf) | INCLUDE — transitive, required by dompdf |
| dompdf/php-svg-lib | 1.0.2 | https://packagist.org/packages/dompdf/php-svg-lib | LGPL-3.0-or-later | © dompdf community; LICENSE + AUTHORS.md in package | LGPL notice obligations (GPL-3.0-or-later text applies to this package alone) | **Unmodified** | No open advisories at this version | INCLUDE — transitive, required by dompdf (SVG support) |
| masterminds/html5 | 2.11.0 | https://packagist.org/packages/masterminds/html5 | MIT | © 2014 Masterminds; LICENSE.txt in package | MIT: retain copyright + permission notice | **Unmodified** | None known at this version | INCLUDE — HTML5 parser used by dompdf |
| sabberworm/php-css-parser | v9.5.0 | https://packagist.org/packages/sabberworm/php-css-parser | MIT | © 2011 Raphael Schweikert / sabberworm; LICENSE in package | MIT notice retention | **Unmodified** | None known at this version | INCLUDE — CSS parser used by dompdf |

## Bundled fonts

| Asset | Source | License | Notes |
|---|---|---|---|
| DejaVu Sans / Serif / Mono family (TTF + UFM metrics) | Ships inside `vendor/dompdf/dompdf/lib/fonts/` | DejaVu fonts are free/redistributable under their permissive license (Bitstream Vera-derived; see the package's `lib/fonts/mustRead.html`) | Redistribution **permitted**; copyright notices retained in the dompdf package. Font **subsetting enabled** at render time, so distributed PDFs embed subsets, not the full faces. |

## Dev-only tools (NOT distributed in the runtime ZIP)

| Tool | Version | License | Distribution |
|---|---|---|---|
| esbuild | ^0.28.0 (frontend devDependency) | MIT | **Not distributed in the runtime ZIP** — build-time only; its output (admin.js/admin.css) is our own code |
| TypeScript | ^5.9.0 (frontend devDependency) | Apache-2.0 | **Not distributed in the runtime ZIP** — build-time only |
| Composer | 2.10.3 (build tooling) | MIT | Build-time only |
| poppler-utils / curl / wp-cli (test tooling) | various | various | Test-time only |

Note: the source ZIP (`tradesheet-studio-source-v1.0.0.zip`) contains the
build configuration (`frontend/package.json`, `bun.lock`) which references
the dev-only tools above; they remain devDependencies and are not vendored
into the source ZIP (`node_modules` is excluded).

## GPL compatibility conclusion

- MIT packages: unconditionally GPL-2.0-compatible.
- LGPL-2.1 / LGPL-2.1-or-later / LGPL-3.0-or-later packages: distributed
  **unmodified**, separately identifiable in `vendor/` with their own
  license texts, consumed via Composer's autoloader. LGPL §4 distribution
  alongside GPL-2.0-or-later works is satisfied: corresponding source for
  each LGPL package is available from Packagist (linked above), no LGPL
  component is statically modified or relicensed, and notices are preserved
  in `THIRD-PARTY-NOTICES.md` and inside each package directory.
- The plugin's own code carries no obligation conflicts; the LICENSE file
  (GPL-2.0-or-later) and THIRD-PARTY-NOTICES.md ship in both ZIPs, and the
  source ZIP provides the complete corresponding source for our own code.

## Advisory audit summary

Performed via Composer/Packagist on 2026-10-16: **dompdf < 3.1.6 carries 6
published advisories** (CVE-2026-59943/59942/59941/56722/55555/55554).
Constraint `^3.1.6` in `composer.json` and the vendored `v3.1.6` clear all
six. Rationale: `docs/architecture/PDF_ENGINE_DECISION.md`.

## Verification pointers

- Versions/licenses: `plugin/tradesheet-studio/vendor/composer/installed.json`
  (dompdf/dompdf v3.1.6 LGPL-2.1; php-font-lib 1.0.2 LGPL-2.1-or-later;
  php-svg-lib 1.0.2 LGPL-3.0-or-later; masterminds/html5 2.11.0 MIT;
  sabberworm/php-css-parser v9.5.0 MIT).
- License texts: each `vendor/<vendor>/<package>/LICENSE*` file.
- Attribution blocks: `THIRD-PARTY-NOTICES.md` in the repository root.
