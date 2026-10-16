# Freemius Setup Plan

**Status: CONDITIONAL — pending owner credentials. No Freemius account,
credentials, checkout or payout exists yet. Nothing below has been executed
against Freemius; every step marked (sandbox) must be run against the
Freemius **sandbox** first and is clearly labeled as such.**

## Design constraint (decided)

**No Freemius SDK dependency in the core plugin.** The distributed plugin
remains a clean GPL-2.0-or-later product with zero phone-home code.
Distribution model:

```
customer pays on Freemius-hosted checkout
        └─► Freemius delivers OUR pre-built licensed ZIP
             (same artifact as the public ZIP, license notice appended)
```

- The **runtime code is identical** to the public/source build; licensing
  is a packaging + delivery concern, not a code branch.
- This keeps GPL compliance trivial (the GPL lets any recipient share the
  copy; our sale sells packaging, updates and support — see
  `docs/commercial/PRICING_HYPOTHESIS.md`).

## Steps (owner, after account creation)

### A. Account & product (Freemius dashboard)

1. Create the Freemius account (owner) — **BLOCKED: credentials not set up.**
2. Create product: name *TradeSheet Studio*, type WordPress plugin, pricing
   $49 launch / $79 regular (single site; optional 5-site and unlimited
   tiers later), currency USD.
3. Configure the **Freemius-hosted checkout** (they act as merchant of
   record for their channel — note in
   `docs/legal/SELLER_COMPLIANCE_CHECKLIST.md` §4 applies).
4. Payout details + tax interview — **BLOCKED: not set up** (this is where
   MoR tax handling is configured).

### B. Licensed ZIP delivery pipeline (our side)

1. Extend `scripts/build-release.sh` (or a wrapper `build-release-licensed.sh`)
   to produce a third artifact from the same inputs:
   `tradesheet-studio-v{VERSION}-licensed.zip` =
   the installable ZIP + an added `LICENSE-NOTICE.txt` stating the delivery
   channel and purchase reference, and the same checksum discipline
   (SHA256SUMS.txt line).
2. Upload the licensed ZIP as the downloadable in the Freemius product.
   The public ZIP and source ZIP remain distributed from our own site
   (GPL corresponding source).
3. Every release: rebuild via the script, update the downloadable, keep
   `dist/release-manifest.json` as the record.

### C. Integration without SDK (verification hooks, optional)

- If download protection / updates are desired without the SDK: keep using
   Freemius's hosted download management only (delivery), and publish
   update notices through our own site's RSS/changelog (README/CHANGELOG).
- If later the owner decides to adopt the Freemius SDK, it must be a
   **separate distribution flavor** (fork of the build with the SDK
   required), never merged into core — revisit this document then.

### D. Sandbox validation (clearly labeled: SANDBOX)

After credentials exist, run in the Freemius **sandbox**:

1. (sandbox) Create a sandbox product + $0 test plan.
2. (sandbox) Walk the hosted checkout end-to-end with a test payment
   method.
3. (sandbox) Confirm the licensed ZIP downloads post-purchase and installs
   in the WP test environment (`/home/z/wp-test`), activating cleanly.
4. (sandbox) Confirm refund flow swaps download access off.
5. Record each sandbox result here with dates. **Nothing may be marked
   VERIFIED until run.**

### E. Go-live checklist

- [ ] Freemius account verified (owner)
- [ ] Product + pricing configured
- [ ] Licensed-ZIP build step produced and checksummed
- [ ] Sandbox checkout/download/refund validated (dates recorded)
- [ ] Payouts + MoR tax terms reviewed (see compliance checklist §4)
- [ ] Support channel linked (Freemius support inbox or
      hi.aditya.dev@gmail.com per `docs/support/SUPPORT.md`)
- [ ] Marketing-site purchase CTA enabled (currently intentionally
      disabled)

## Current blockers

| Blocker | Owner action |
|---|---|
| No Freemius account/credentials | Create account; store credentials outside the repo (never commit) |
| No payout configuration | Complete payout + tax steps in the dashboard |
| Licensed-ZIP build step not yet written | Engineering task after account exists (extends build-release.sh) |
| Sandbox validation not executed | Run section D; record results with dates |
