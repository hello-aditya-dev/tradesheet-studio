# Marketplace Readiness

Per-channel status and what remains. Overall principle: only the self-owned
channel is fully under our control; every third-party channel is gated on
external platform decisions or owner credentials.

| Channel | Status | What remains |
|---|---|---|
| **Self-hosted (dev-aditya.com)** | **CONDITIONAL (product ready, commerce blocked)** | Product ZIP, source ZIP, docs and marketing site exist. Blocked on: checkout gateway credentials, website privacy policy (GDPR/DPDP), refund policy publication, seller-tax review. Purchase CTA intentionally disabled until these clear. |
| **Freemius** | **CONDITIONAL — pending owner account** | Plan finalized (`docs/commercial/FREEMIUS_SETUP.md`) with no SDK in core; blocked on account/credentials/payouts; sandbox validation unexecuted. |
| **wordpress.org (free directory)** | **NOT SUBMITTED (decision open)** | GPL-compatible and technically aligned, but listing there gives the plugin away at $0 — an explicit business decision is required before submitting (slug availability verified 2026-10-16: `tradesheet` and `tradesheet-studio` both unoccupied — see `docs/legal/TRADEMARK_RESEARCH.md`). |
| **WooCommerce Marketplace** | **CONDITIONAL — pending application** | Product is WooCommerce-native and GPL. Requires account/application, review fee schedule, and their approval process; nothing submitted yet. |
| **Codester** | **NOT APPROVED — GPL clarification required** | Do not list until Codester confirms in writing that selling GPL-2.0-or-later software under their standard terms is acceptable (their default exclusive-license assumptions conflict with GPL freedoms). |
| **CodeCanyon (Envato)** | **EXCLUDED by decision** | Not pursued. |

## Cross-channel readiness common to all paid channels

| Item | Status |
|---|---|
| Installable ZIP + source ZIP + checksums + manifest | VERIFIED (`docs/release/RELEASE_PROCESS.md`) |
| Documentation suite (user/install/architecture/security) | VERIFIED (this docs tree) |
| Third-party license audit & notices | VERIFIED (`docs/legal/THIRD_PARTY_LICENSE_AUDIT.md`, `THIRD-PARTY-NOTICES.md`) |
| Executed test evidence + honest compatibility matrix | VERIFIED (`docs/testing/`) |
| Security disclosure channel | VERIFIED (`SECURITY.md`) |
| Trademark clearance | CONDITIONAL (`docs/legal/TRADEMARK_RESEARCH.md`) |
| Seller tax posture (India) | BLOCKED pending professional review (`docs/legal/SELLER_COMPLIANCE_CHECKLIST.md`) |
| Checkout/payouts | BLOCKED — no credentials anywhere yet |
| Screenshots & demo script | SPEC READY — owner must capture from the demo store (`docs/commercial/PRODUCT_LISTING.md`) |
| 1,000-SKU performance benchmark | PENDING — not executed; no numbers to publish |

## Recommended sequencing

1. Clear self-hosted blockers (privacy policy, refund policy, CA review,
   gateway) → enable purchase CTA.
2. Freemius sandbox validation → go live on Freemius.
3. Revisit wordpress.org / WooCommerce Marketplace as a strategic decision
   (free distribution vs paid reach) after first sales data.
4. Codester only after the GPL clarification letter.
