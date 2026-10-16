# Contributing

Thanks for considering a contribution to TradeSheet Studio. The project is
GPL-2.0-or-later; by submitting a pull request you agree your contribution
is licensed under the same terms (see below).

## Development environment

| Tool | Used for | Notes |
|---|---|---|
| PHP ≥ 8.1 (CLI) | linting, unit tests, PDF smoke | the reference sandbox used a portable PHP 8.4.5 at `/home/z/tools/php` |
| Composer | vendoring the PDF renderer | only needed when refreshing `vendor/`; runtime sites never need it |
| Bun (or npm/Node) | building the admin app | `frontend/` uses esbuild + TypeScript only |
| poppler-utils (`pdfinfo`, `pdftotext`) | verifying PDF output | used by the smoke-test workflow |
| wp-cli + a WordPress test install | integration suite | reference install: WordPress 7.1.3 + WooCommerce 11.2.1 + SQLite drop-in |

The plugin's `composer.json` pins the platform to PHP 8.1.99 so the vendor
bundle stays 8.1-compatible regardless of your build PHP.

## Getting started

```bash
git clone git@github.com:hello-aditya-dev/tradesheet-studio.git   # (repo is PRIVATE during pre-launch)
cd tradesheet-studio
composer install --no-dev --prefer-dist          # refreshes vendor/ (Dompdf ^3.1.6)
cd frontend && bun install && bun run typecheck && bun run build
```

Compiled artifacts (`plugin/tradesheet-studio/assets/admin.js`,
`admin.css`) are committed — rebuild them whenever you touch `frontend/src`.

## Test commands (run before every PR)

```bash
# Unit suite (pure PHP, no WordPress needed)
/home/z/tools/php tests/unit/run.php             # or: php tests/unit/run.php

# Lint all plugin PHP
find plugin/tradesheet-studio/src plugin/tradesheet-studio/templates -name '*.php' -exec php -l {} \;

# PDF smoke (needs vendor/)
php tests/pdf-smoke.php
pdfinfo /tmp/tss-*.pdf                           # verify outputs manually when changing templates

# Integration golden path (inside a WP test install with the plugin active)
wp eval-file /path/to/tradesheet-studio/tests/integration/golden-path.php
# Run it in BOTH order-storage modes (legacy CPT and HPOS) when touching
# anything order-related.
```

Full plan and environment details: `docs/testing/TEST_PLAN.md`.

## Pull-request expectations

1. **Scope**: one logical change per PR; describe the why, not just the what.
2. **Tests**: bug fixes come with a regression test in the matching suite;
   new pricing/state-machine/CSV logic belongs in `tests/unit/run.php`;
   end-to-end behavior belongs in the integration golden path.
3. **No floating point in money paths** — use `src/Support/Money.php`.
   All SQL must go through `$wpdb->prepare()`.
4. **State machines**: never add a transition to
   `PacketStateMachine`/`QuoteRepository::TRANSITIONS` without unit
   coverage and a note in `docs/architecture/STATE_MACHINES.md`.
5. **Security-relevant changes** (tokens, CSV, PDF, streaming,
   capabilities): update `docs/security/THREAT_MODEL.md` and, when
   testable, extend the security checklist in
   `docs/security/SECURITY_TEST_REPORT.md`.
6. **Docs**: update the affected document in `docs/` in the same PR
   (the docs are part of the product).
7. **Dependencies**: runtime deps must be GPL-compatible (LGPL/MIT);
   never introduce AGPL or proprietary code into `vendor/`; re-run the
   advisory audit and update `docs/legal/THIRD_PARTY_LICENSE_AUDIT.md`.
8. **Commits**: `feat:` / `fix:` / `test:` / `docs:` / `release:`
   prefixes; no credentials or tokens in any file or commit message
   (a secret scan runs before every push).
9. **Frontend**: `bun run typecheck` must pass (strict); the bundle must
   keep using `window.wp.element` (no bundled React).

## Code style

PHP: WordPress coding style (tabs, Yoda conditions where applicable),
`declare(strict_types=1)` in every file, escaping at output. TypeScript:
strict mode; hand-written `wp` typings in `frontend/src/types/wp.d.ts`.

## License of contributions

TradeSheet Studio is licensed **GPL-2.0-or-later**. Contributions are
accepted under the same license; you retain your copyright. Bundled
third-party components remain under their own licenses (see
`THIRD-PARTY-NOTICES.md`).
