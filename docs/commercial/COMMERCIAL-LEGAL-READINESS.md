# Commercial & Legal Readiness

Status vocabulary: **VERIFIED** (evidence exists in-repo), **CONDITIONAL**
(pending an external step/decision), **BLOCKED** (external dependency not
yet available), **NOT APPLICABLE** (out of scope by decision).

| # | Area | Status | Evidence / What remains |
|---|---|---|---|
| 1 | **Code ownership** | **VERIFIED** | Original work; AI-assisted development is disclosed (development used AI tooling; authorship and all decisions rest with the owner, Aditya). No third-party proprietary code is included; repo history is the record. |
| 2 | **GPL compliance** | **VERIFIED** | `LICENSE` (GPL-2.0-or-later) at root and shipped in both ZIPs; `THIRD-PARTY-NOTICES.md` shipped in both ZIPs; the **source ZIP provides complete corresponding source** for the distributed binaries; license headers in the plugin header; README states GPL freedoms are not removed by purchase. |
| 3 | **Dependency licensing** | **VERIFIED** | `docs/legal/THIRD_PARTY_LICENSE_AUDIT.md` — 5 runtime packages, all unmodified, LGPL/MIT mix compatible with GPL-2.0-or-later distribution; license texts retained in `vendor/`; advisory-clean dompdf 3.1.6. |
| 4 | **Asset licensing** | **VERIFIED** | All product assets are original (inline SVG diagrams, our own templates/CSS); bundled DejaVu fonts ship under their redistributable license inside dompdf (see audit); demo data is synthetic and labeled. |
| 5 | **Trademark** | **CONDITIONAL** | Preliminary searches done (`docs/legal/TRADEMARK_RESEARCH.md`, 2026-10-16: wp.org plugin+theme directories 0 results, both slugs unoccupied, no exact-phrase web results). **No filing made; registries not record-searched — clearance NOT declared.** Owner decision required (file vs proceed ™-only). |
| 6 | **Privacy — plugin** | **VERIFIED** | No telemetry, no phone-home, no external accounts; WordPress personal-data export/eraser integration implemented and tested (integration suite group 11). |
| 7 | **Privacy — product website** | **CONDITIONAL** | dev-aditya.com site needs a written, reviewed privacy policy (GDPR/DPDP Act 2023) covering contact/checkout/cookies — see `docs/legal/SELLER_COMPLIANCE_CHECKLIST.md` §5. |
| 8 | **Marketplace compatibility — Freemius** | **CONDITIONAL** | Integration plan exists WITHOUT SDK dependency in core (`docs/commercial/FREEMIUS_SETUP.md`); pending owner account/credentials; sandbox steps are clearly labeled. |
| 9 | **Marketplace compatibility — Codester** | **CONDITIONAL (policy)** | NOT APPROVED unless GPL clarification is obtained: Codester's standard exclusive-license assumptions conflict with GPL-2.0-or-later distribution; do not list before written confirmation that selling GPL-licensed software under their terms is acceptable. |
| 10 | **Marketplace compatibility — WooCommerce Marketplace** | **CONDITIONAL** | Pending application/owner decision; product is technically aligned (WooCommerce-native, GPL), but no application has been submitted. |
| 11 | **Marketplace compatibility — CodeCanyon** | **NOT APPLICABLE** | Excluded by decision. |
| 12 | **Seller tax (India)** | **BLOCKED** | Pending professional review (CA) — GST registration thresholds for e-commerce software supply, SAC code, income-tax regime, export/LUT strategy: `docs/legal/SELLER_COMPLIANCE_CHECKLIST.md`. |
| 13 | **Checkout & payouts** | **BLOCKED** | No gateway credentials, no Freemius account, no payout configuration exists yet. Nothing has been set up; the purchase CTA on the marketing site is intentionally disabled until launch. |
| 14 | **Security posture disclosure** | **VERIFIED** | `docs/security/THREAT_MODEL.md` + executed `SECURITY_TEST_REPORT.md`; `SECURITY.md` at root with reporting channel. |
| 15 | **Version compatibility honesty** | **VERIFIED** | `docs/testing/COMPATIBILITY_MATRIX.md` marks every untested cell NOT TESTED; README links to it. |

## Summary of blockers before first paid sale

1. Freemius account (or alternative checkout) — credentials + payout setup.
2. CA review of GST/income-tax/export posture (seller compliance §1–§3).
3. Website privacy policy written and reviewed.
4. Trademark decision (file or proceed ™-only with recorded searches).
5. Optional: Codester GPL clarification if that channel is pursued.

Nothing above blocks public announcement of the product; the blocked items
gate *transacting money*, not visibility.
