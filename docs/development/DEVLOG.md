# Development Ledger

A concise, honest record of execution phases, decisions and evidence.
Detailed requirements tracking lives in `docs/testing/ACCEPTANCE_LEDGER.md`.

## Phase 0 — Environment & repository (status: COMPLETE)

- Environment recon: git 2.47.3, node 24.21, bun 1.3.14, zip, python3,
  poppler-utils available. System PHP/gh/composer/mysql absent; no sudo.
- Provisioned a portable PHP 8.4.5 CLI (static build, with `dom`,
  `mbstring`, `sqlite3`, `gd`, `zip`, `bcmath`, `curl`, `fileinfo`) and
  Composer 2.10.3 under `/home/z/tools` — used for local linting, vendoring,
  unit tests and PDF smoke tests without requiring root.
- GitHub credential verified as `hello-aditya-dev` (HTTP 200 on GET /user).
- Repository created as PRIVATE (`POST /user/repos` → 201), default branch
  `main`, remote `origin` configured.
- Local repository initialized at `/home/z/tradesheet-studio` with
  repository-local identity `Aditya <hi.aditya.dev@gmail.com>` (both author
  and committer fields verified with `git log --format=fuller`).
- The GitHub credential is handled only via a shell environment variable and
  is never written to any file (secret scan run before every push).

## Phase 1 — Research and architecture (status: COMPLETE)

- Version targets: WordPress ≥ 6.4, WooCommerce ≥ 8.0, PHP ≥ 8.1.
  Rationale: `docs/engineering/compatibility-research.md`.
- PDF engine decision: Dompdf (LGPL-2.1-or-later) with bundled DejaVu fonts
  (their permissive free licenses) — vendored via Composer at build time.
  Details: `docs/architecture/PDF_ENGINE_DECISION.md`.
- Money handling: decimal strings with scale-4 storage; integer-scaled
  arithmetic in `src/Support/Money.php` (no binary floating-point
  arithmetic anywhere in pricing paths).
- State machines: `docs/architecture/STATE_MACHINES.md`.
- Security: threat model first written, then controls implemented —
  `docs/security/THREAT_MODEL.md`.

## Phase conventions

- Every milestone is committed with the subject conventions from the master
  directive (`feat:`, `test:`, `docs:`, `release:` prefixes).
- Every status claim in this ledger points to executed evidence or is
  explicitly marked BLOCKED / NOT TESTED.

## Phase 2 — Core domain implementation (status: COMPLETE)

- Database: 12 `tss_*` tables via dbDelta, versioned by
  `tss_schema_version` (`2026-10-16-1`); all access through `$wpdb->prepare()`
  (`src/Database/Schema.php`, `docs/architecture/DATABASE_SCHEMA.md`).
- Money: scale-4 decimal strings, integer-scaled arithmetic, percent scale
  2, `Money::fromDb` canonicalization across DB drivers
  (`src/Support/Money.php`).
- Pricing engine: single deterministic winner — packet override >
  customer-specific rule > price-book rule (variation > product >
  book-wide, then priority desc, then lowest rule id) > WooCommerce base;
  qty-break tiers replace the winner price (`src/Pricing/PricingEngine.php`).
- Packet lifecycle: PacketStateMachine (draft→issued→submitted→in_review→
  approved→accepted→ordered + expired/revoked/cancelled/archived;
  version-level supersession), immutable version snapshots with SHA-256
  content checksums (`src/Packets/`).
- REST layer: namespace `tradesheet-studio/v1`, five controllers, every
  route permission-checked (`src/Api/`).

## Phase 3 — Documents & PDF engine (status: COMPLETE)

- Dompdf decision executed: `^3.1.6` vendored (v3.1.6 exactly), clearing
  6 published advisories affecting < 3.1.6 (CVE-2026-59943/59942/59941/
  56722/55555/55554) — `docs/architecture/PDF_ENGINE_DECISION.md`.
- Renderer hardened: remote resources disabled, data-URI images from local
  attachments, inline PHP restricted to our own page-numbering templates
  (`src/Documents/PdfRenderer.php`).
- CSV order sheet: formula-injection neutralization on export
  (`Utils::csvSafe`), strict re-validation on import (quantities are the
  only buyer input honored) (`src/Documents/CsvOrderSheetService.php`).
- Documents stored privately under `uploads/tradesheet-studio-docs/`
  (.htaccess deny + index.php) and streamed through permission-checked
  endpoints with realpath containment (`src/Documents/DocumentService.php`).

## Phase 4 — Buyer portal & security hardening (status: COMPLETE)

- Portal at `/tradesheet/packet/{REF}/`: token (SHA-256 at rest, expiring,
  revocable), owner, or merchant access; anti-enumeration (uniform 404s);
  no-cache/no-index/no-referrer headers; rate-limited POST actions;
  2 MiB / 2000-row CSV caps (`src/Buyers/`).
- Capability layering on `manage_woocommerce`; nonce enforcement for
  cookie-authenticated modes (`src/Security/`).
- Idempotent quote→order conversion at negotiated prices with atomic
  claims and stale-conversion reconciliation (`src/Orders/
  WooCommerceOrderBridge.php`).
- HPOS compatibility declared at boot and later tested in both modes.

## Phase 5 — Admin app & marketing site (status: COMPLETE)

- Admin SPA (frontend/): esbuild-bundled TypeScript, React from
  `window.wp.element` (no bundled React — devDependencies only esbuild +
  typescript); ten views; compiled to `assets/admin.js` (~141 KB) +
  `admin.css`; contract validated by source review plus a jsdom mount test
  of the real bundle (no live WP available during that task — recorded
  honestly then).
- Marketing single-page site at the user-facing surface (task 6-b): 14
  sections, synthetic-data and non-affiliation disclaimers, purchase CTA
  intentionally disabled until launch; `bun run lint` clean.
- Demo dataset (fixtures/demo-dataset.php): synthetic "Volt & Wire Trading
  Co." + buyers Krishna Electricals / Shakti Industries — labeled as such
  everywhere.

## Phase 6 — Testing & verification (status: COMPLETE; pending items recorded)

Executed 2026-10-16 (WordPress 7.1.3, WooCommerce 11.2.1, PHP 8.4.5,
SQLite drop-in; PHPUnit NOT used — bespoke harnesses):

- Unit suite `tests/unit/run.php`: **105 assertions PASSED**.
- Integration `tests/integration/golden-path.php`: **78 assertions PASSED,
  run TWICE** — legacy CPT order storage and HPOS; order #94 verified at
  the negotiated price; idempotency held under both.
- PDF smoke `tests/pdf-smoke.php`: **3 valid PDFs** (trade_table landscape
  A4, visual_catalog, technical_sheet) verified with poppler; page
  numbering and ₹ Unicode extraction confirmed.
- HTTP security suite (curl, live server): 401s unauthenticated, nonce
  enforcement, anti-enumeration 404s, token authorization, security
  headers, token-gated downloads, rate limiting observed, admin mount,
  deactivate/reactivate preserves data (11→11 packets).
- PHP lint: 46/46 files.
- NOT TESTED (recorded): older WP/WC/PHP versions, production
  MySQL/MariaDB, PHP 8.1–8.3 runtimes (composer platform pinned 8.1.99),
  block vs classic checkout, third-party themes, payment gateway flows,
  and the 1,000-SKU benchmark (never executed — no numbers exist).

## Phase 7 — Release engineering & documentation (status: COMPLETE)

- `scripts/build-release.sh`: lint + unit gates, required-file checks,
  installable ZIP (runtime + bundled docs) and source ZIP (corresponding
  source), SHA256SUMS.txt, release-manifest.json with commit SHA —
  `docs/release/RELEASE_PROCESS.md`.
- Complete documentation suite written (installation, product, architecture,
  security, testing, legal, commercial, engineering, support, release) plus
  root files `THIRD-PARTY-NOTICES.md`, `SECURITY.md`, `CONTRIBUTING.md`.
- Trademark preliminary searches executed 2026-10-16 (wp.org plugin+theme
  directories: 0 results; both slugs unoccupied; no exact-phrase web
  results) — CONDITIONAL, clearance NOT declared
  (`docs/legal/TRADEMARK_RESEARCH.md`).
- Commercial readiness assessed: code ownership / GPL compliance /
  dependency & asset licensing VERIFIED; trademark, privacy policy,
  Freemius, WooCommerce Marketplace CONDITIONAL; Codester gated on GPL
  clarification; seller tax and checkout/payouts BLOCKED
  (`docs/commercial/COMMERCIAL-LEGAL-READINESS.md`).
