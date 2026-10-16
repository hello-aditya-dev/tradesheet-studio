# Security Policy

## Supported version

| Version | Supported |
|---|---|
| 1.0.x (current release line) | **Yes** |
| Anything older / pre-release development snapshots | No |

## Reporting a vulnerability

**Do not open public issues for security problems.**

Email: **hi.aditya.dev@gmail.com** — include `[security]` in the subject
line. Please provide:

- a description of the issue and its impact,
- reproduction steps (packet/quote references, HTTP requests, or a
  minimal script),
- affected version (see **WooCommerce → TradeSheet Studio →
  Diagnostics**), plus WordPress/WooCommerce/PHP versions,
- any PoC or payload that is safe to share.

### What to expect

| Stage | Target |
|---|---|
| Acknowledgment | within **72 hours** |
| Triage + severity assessment | within **7 days** |
| Fix or mitigation timeline | communicated after triage; critical issues prioritized for the next patch release |
| Coordinated disclosure | after a fix ships, with credit to the reporter if desired |

Please avoid scanning third-party hosting or accounts, and do not test
against stores you do not own.

## Scope

**In scope:**

- The plugin `tradesheet-studio` as distributed (PHP `src/`, templates,
  compiled `assets/`, bundled `vendor/` as shipped unmodified).
- The buyer portal, REST API surface (`tradesheet-studio/v1`), token
  issuance/validation, CSV import path, PDF generation pipeline, document
  streaming, quote→order conversion, and the admin application.

**Out of scope (general):**

- WordPress core, WooCommerce core and their standard hardening topics.
- Vulnerabilities in Dompdf or other bundled libraries **as released**
  — report those upstream; do alert us if a bundled version becomes
  affected (we pin advisory-clean versions; see
  `docs/architecture/PDF_ENGINE_DECISION.md` and
  `docs/legal/THIRD_PARTY_LICENSE_AUDIT.md`).
- The marketing website (separate surface) except where it exposes this
  plugin's artifacts.
- volumetric DoS, social engineering, physical attacks.

## Known security-relevant design decisions

- Buyer portal tokens: SHA-256 hashed at rest, expiring, revocable;
  anti-enumeration (uniform denials).
- CSV uploads: quantities are the only buyer input honored; prices always
  come from the immutable server-side snapshot; formula injection
  neutralized in both directions.
- PDF rendering: remote resources disabled; images embedded as data URIs
  from local attachments.
- Documents: private storage with denied direct web access and
  permission-checked streaming with path containment.
- All SQL prepared; merchant REST routes capability- and nonce-gated;
  public portal actions rate limited.

The threat model is documented in `docs/security/THREAT_MODEL.md` and the
executed security tests in `docs/security/SECURITY_TEST_REPORT.md`
(including an explicit NOT TESTED list — no penetration test was
performed).
