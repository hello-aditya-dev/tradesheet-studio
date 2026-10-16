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
