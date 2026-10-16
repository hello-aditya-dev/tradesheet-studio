# Pricing Hypothesis — $49 launch / $79 regular

Status: **hypothesis** — not validated by sales data. This document exists
so the owner can revisit it against evidence after launch.

## The numbers

| Tier | Price | Window |
|---|---|---|
| Launch | **$49** | Limited launch window (e.g. first 4–8 weeks or first N customers) |
| Regular | **$79** | After the launch window |

Single-site license scope; multi-site and bundle tiers are deliberately
**not** priced yet (decide after launch data).

## Rationale

- Comparable WooCommerce wholesale/B2B quote/order plugins sit roughly in
  the $49–$149 single-site band; entering at the accessible end while the
  product has a 1.0 feature set (no multi-currency, no multi-site tooling)
  is honest positioning.
- $49 lowers first-buyer friction for an unknown solo developer brand; $79
  regular keeps the product out of "cheap utility" perception.
- The plugin solves a revenue-integrity problem (price drift between
  catalog/quote/order), which supports B2B willingness-to-pay above
  commodity plugins.
- Costs to the buyer are one-time in v1 (no subscription infra exists;
  updates ship as releases).

## GPL-aware positioning language (use verbatim-ish)

> **You buy packaging, documentation, updates and support — the GPL
> freedoms remain.**

- TradeSheet Studio is GPL-2.0-or-later software. Buying it buys you the
  convenient, tested, documented distribution, the release ZIPs with
  checksums, updates, and support from the developer. It does not remove
  the freedoms the GPL grants: you may study, modify and share the code —
  see `THIRD-PARTY-NOTICES.md` for the bundled components' licenses.
- Corresponding source ships as the source ZIP; nothing is obfuscated or
  phone-home-locked (no license keys in v1).

Suggested store copy fragment:

> *No license keys, no call-home, no lock-in. The software you download is
> complete and GPL-licensed. What you're paying for is the packaging,
> documentation, updates and support — and the sustainability of the
> product behind them.*

## What the price must cover (owner's unit economics)

- Development amortization, support time (see
  `docs/support/SUPPORT.md` response targets), marketplace commissions
  (Freemius MoR fee / gateway fees), taxes (see
  `docs/legal/SELLER_COMPLIANCE_CHECKLIST.md` — CA review pending).

## Test plan for the hypothesis

1. Launch at $49 for the announced window; publish the end date.
2. Track: visits→checkout conversion, support load per sale, refund rate.
3. At switch to $79: compare conversion before/after (cohorts, ≥30 days
   each).
4. Decisions to revisit: multi-site tier pricing, bundle (PDF template
   packs), optional paid support tiers — only with data.

## Non-goals (explicit)

- No subscription, no SaaS tier, no license-key enforcement in v1.
- No "pro/basic" code split — one edition, one artifact.
