# Release Process

Builder: `scripts/build-release.sh` — reproducible, gated, checksummed.

## Usage

```bash
cd /home/z/tradesheet-studio
./scripts/build-release.sh [version]        # version defaults to the plugin header
TSS_PHP=/home/z/tools/php ./scripts/build-release.sh   # when `php` is not on PATH
```

## Pre-flight gates (build aborts on failure)

1. **PHP lint** over `plugin/tradesheet-studio/src` and `templates`
   (`$PHP_BIN -l`, override with `TSS_PHP`; default `php`).
2. **Required runtime files present** — bootstrap, uninstall, compiled
   assets (`admin.js`, `admin.css`, `buyer.js`), `vendor/autoload.php`,
   `vendor/dompdf/dompdf/src/Dompdf.php`, all 3 PDF templates + buyer
   portal template, translations, root `LICENSE`,
   `THIRD-PARTY-NOTICES.md`.
3. **Unit test suite** (`tests/unit/run.php`) must pass (skipped only when
   no PHP binary is available in a build container — in our environment it
   runs: 105 assertions).

## Artifacts

| Artifact | Contents |
|---|---|
| `dist/tradesheet-studio-v{VERSION}.zip` | **Installable plugin**: full `plugin/tradesheet-studio/` runtime (PHP src, compiled assets, bundled Dompdf 3.1.6 + dependencies + fonts, templates, pot) — `composer.json/lock` and `.git*` excluded — PLUS bundled docs: `LICENSE`, `THIRD-PARTY-NOTICES.md`, `SECURITY.md`, `README.md`, and the user-facing docs (`docs/installation/INSTALLATION.md`, `QUICK_START.md`, `TROUBLESHOOTING.md`, `docs/product/USER_GUIDE.md`, `CSV_FORMAT.md`). Source maps are deleted; no test fixtures or demo credentials ship. |
| `dist/tradesheet-studio-source-v{VERSION}.zip` | **Complete editable source**: the whole repository minus `.git/`, `.github/`, `node_modules/` (root and frontend), `dist/`, `.DS_Store`. Provides corresponding source (GPL) — PHP, frontend/TS, build config, tests, fixtures, docs, licenses. |
| `dist/SHA256SUMS.txt` | `sha256sum` of both ZIPs |
| `dist/release-manifest.json` | JSON: product, version, `built_at` (UTC), `source_commit` (git SHA at build time, or `unknown` outside a repo), per-artifact bytes/sha256/entry-count/contents description, and the verification block (php_lint, unit_tests, required_files — all `pass`) |

## Reproducibility notes

- Version comes from the plugin header when not passed explicitly — one
  source of truth.
- ZIPs are built from a clean `mktemp` staging dir (rsync-copy of tracked
  runtime files), so stray working-tree files cannot leak into artifacts.
- `built_at` and `source_commit` make each build attributable; the
  checksums make it verifiable. (Byte-identical ZIPs across machines are
  not guaranteed by zip timestamps — verify by SHA256 of a given build,
  and compare `source_commit` + version for equivalence.)
- The manifest's verification block records that the gates ran.

## Release checklist (owner)

1. Update `CHANGELOG.md` and the plugin header version; bump
   `TSS_SCHEMA_VERSION` only when the schema changed (dbDelta is additive).
2. Rebuild frontend if any admin UI changed: `cd frontend && bun run
   typecheck && bun run build`.
3. Re-run the test suites (`docs/testing/TEST_PLAN.md`) at least:
   unit + integration (both storage modes) + pdf smoke.
4. `composer update --no-dev` refresh check: confirm `dompdf/dompdf`
   remains `^3.1.6` and audit is clean (`docs/engineering/compatibility-research.md`).
5. Build: `./scripts/build-release.sh X.Y.Z`; verify
   `dist/SHA256SUMS.txt` and read `release-manifest.json`.
6. Install the installable ZIP into the WP test environment and smoke:
   activate, publish a packet, open the buyer portal, convert a quote.
7. Tag the release in git (orchestrator-owned operation) and attach
   artifacts with their checksums to the release notes.
8. Post-release: update the marketing-site download links and (if using)
   the Freemius downloadable (`docs/commercial/FREEMIUS_SETUP.md`).

## What must never ship

- Credentials, tokens, `.env`-style files (secret scan is run before
  pushes; the builder stages only known runtime files).
- Test fixtures/demo dataset (contains the synthetic business data — used
  for testing/screenshots only, not distributed).
- `node_modules`, build caches, source maps, git metadata.
