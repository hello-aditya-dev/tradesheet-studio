# Seller Compliance Checklist (India-focused)

> **This document is practical guidance with explicit flags where qualified
> professional review is required. It is NOT legal or tax advice.** Items
> marked ⚠ REQUIRE PROFESSIONAL REVIEW must be confirmed with a qualified
> Chartered Accountant / tax practitioner / lawyer before relying on them.

Scope: selling the TradeSheet Studio plugin (downloadable software) from
India to global buyers, potentially via a marketplace (Freemius) and
self-hosted checkout.

## 1. GST registration for selling software ⚠

- Supply of software/electronic services is a **supply of services** under
  GST; downloadable software delivered online is generally taxed as a
  service, not goods.
- **Thresholds:** registration is generally required once aggregate
  turnover crosses the threshold for **inter-state supply of services via
  the e-commerce route being different from intra-state service
  thresholds** — the exact current limits and whether "supply through an
  electronic commerce operator" changes your obligation **must be
  confirmed with a CA** (rules and thresholds have changed multiple times).
- **Marketplace vs self-billing matters (see §3):** if you sell through a
  platform that acts as **merchant of record** (e.g. Freemius for their
  channel), GST treatment of *your* receipts from the platform differs
  from selling directly to end customers.
- Action: compute projected turnover, then ⚠ engage a CA to determine (a)
  whether/when registration is mandatory, (b) correct HSN/SAC code
  (commonly SAC 9973xx/9984xx family for software services — **verify with
  your CA**), (c) invoicing obligations toward the platform vs customers.

## 2. Income tax ⚠

- Revenue from plugin sales is business/professional income; choose the
  right head (business vs professional income) and regime with a CA.
- **Presumptive taxation (Section 44ADA/44AD eligibility)** for small
  service providers can simplify compliance — eligibility and limits
  change; ⚠ confirm current applicability.
- Advance-tax instalments once liability exceeds thresholds; TDS on
  platform payouts where applicable.
- Action: ⚠ CA review before first sale; decide books-of-account approach
  for marketplace payouts (gross vs net-of-commission recognition).

## 3. Export of services notes ⚠

- Sales to foreign buyers may qualify as **export of services** (zero-rated
  under GST when place-of-supply is outside India, payment received in
  convertible foreign exchange, and supplier+recipient are not merely
  establishments of the same person). If GST-registered, zero-rating is via
  **Letter of Undertaking (LUT)** without charging IGST.
- Selling **through a merchant-of-record platform** (Freemius is MoR for
  their channel): the platform invoices the end customer; you invoice the
  platform. Whether your supply to the platform is itself an export depends
  on the platform's location and contract — ⚠ confirm with a CA.
- Self-hosted sales to foreign customers: keep evidence (invoices, FIRC /
  payment-gateway settlement records) for export status.
- Action: ⚠ CA review of your two channels (self-hosted vs Freemius-MoR)
  and LUT setup if registered.

## 4. Merchant-of-record vs self-billing distinction

| Model | Who invoices the buyer | Your compliance surface |
|---|---|---|
| **Freemius channel** — Freemius acts as **merchant of record** for their channel | Freemius | You invoice/receive payouts from Freemius; VAT/GST on end-customers is handled by the MoR for that channel (confirm current terms in the Freemius agreement) |
| **Self-hosted checkout** (own gateway on the product site) | You | You handle tax invoicing, GST (if registered), refunds, chargebacks and consumer obligations for each buyer jurisdiction you sell to |
| Other marketplaces (Codester / WooCommerce Marketplace, if pursued) | Per marketplace terms — **read each one** | ⚠ varies; some are listing platforms (you are the seller), not MoR |

Action: keep this table current per channel; when the Freemius agreement
arrives, file the MoR clause reference here.

## 5. Privacy compliance for the product website (GDPR / DPDP Act 2023)

- The **plugin itself performs no telemetry** (VERIFIED — no analytics,
  no phone-home, no account on our servers; privacy policy can state this
  factually).
- The **product website** (dev-aditya.com marketing/purchase site) DOES
  collect personal data (contact email, checkout, cookies) and needs:
  - an accurate privacy policy (what is collected, why, retention, contact);
  - lawful basis / consent records for marketing email;
  - **India DPDP Act 2023**: consent notices and purpose limitation for
    digital personal data; data-principal rights handling process;
  - **GDPR** (EU buyers): controller obligations, data-subject requests,
    cookie banner where cookies beyond strictly-necessary are used,
    processor terms with any EU-relevant vendors.
  - ⚠ have the published policy reviewed by a professional before launch.
- Refund/chargeback data, support inbox retention: define retention windows.

## 6. Refunds and consumer protection

- Publish an explicit **refund policy** on the purchase page (marketplaces
  often impose their own minimum: e.g. Freemius/Codester storefront terms
  — follow the channel's policy when selling there).
- India: **Consumer Protection (E-Commerce) Rules** apply to e-commerce
  service providers — applicability to digital-product self-selling
  depends on scale and classification ⚠; keep grievance-contact details
  visible on the site.
- EU buyers: 14-day right-of-withdrawal has a commonly used
  digital-content waiver with express consent — ⚠ professional review
  before relying on it.

## 7. Product claims & marketing hygiene

- Compatibility claims must match `docs/testing/COMPATIBILITY_MATRIX.md`
  (only executed cells may be claimed).
- No WordPress/WooCommerce affiliation claims (non-affiliation statement
  present on the site footer and README).
- Synthetic demo data ("Volt & Wire Trading Co.", "Krishna Electricals",
  "Shakti Industries") must remain clearly labeled in screenshots and
  demos.

## 8. Checklist status

| Item | Status | Owner action |
|---|---|---|
| GST registration decision | **BLOCKED on professional review** | Engage CA ⚠ |
| SAC/HSN code confirmation | **BLOCKED on professional review** | CA ⚠ |
| Income-tax regime & advance tax plan | **BLOCKED on professional review** | CA ⚠ |
| Export/LUT strategy (if registered) | **BLOCKED on professional review** | CA ⚠ |
| Freemius MoR terms review | **BLOCKED — account/contract not yet set up** | After signup |
| Website privacy policy (GDPR/DPDP) | **CONDITIONAL — must be written & reviewed** | Owner + review ⚠ |
| Refund policy publication | **CONDITIONAL — draft before first sale** | Owner |
| Trademark | **CONDITIONAL — see docs/legal/TRADEMARK_RESEARCH.md** | Owner decision |
| Plugin telemetry audit | **VERIFIED — no telemetry in the plugin** | None |

> Nothing in this file should be represented as legal, tax or accounting
> advice; it exists so the owner knows which professionals to engage and
> what to ask them.
