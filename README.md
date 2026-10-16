# TradeSheet Studio

**The wholesale catalog that becomes an order.**

TradeSheet Studio is a commercial WordPress plugin for WooCommerce that lets
wholesale merchants build **DealPackets** — buyer-specific, versioned commercial
offers that keep a branded PDF catalog, a CSV order sheet, a protected online
buyer portal, quotation revisions, and the final WooCommerce order anchored to
**one immutable commercial snapshot**.

> Send one authoritative offer per buyer. Prevent prices, terms, and product
> data from silently diverging between the catalog you send, the quote you
> negotiate, and the order you fulfill.

## Core workflow

1. **Price books** — define wholesale price books (fixed overrides, percentage
   discounts, quantity breaks, minimum quantities) and assign WooCommerce
   customers to them.
2. **DealPacket composer** — a guided five-stage workflow: choose the buyer,
   choose products/variations, configure pricing and commercial terms, choose
   document presentation, then preflight-validate and publish.
3. **Immutable snapshot** — publishing freezes the commercial terms (products,
   variations, SKUs, names, prices, quantity rules, currency, validity) with a
   SHA-256 content checksum. Changes create a *new version* — never rewrite an
   issued offer.
4. **Documents** — a real PDF catalog (three templates), a CSV order sheet, and
   a ZIP bundle, delivered through protected, token-gated endpoints.
5. **Buyer portal** — a responsive, self-hosted page where the buyer adjusts
   quantities, uploads a completed CSV, and submits a quotation request.
6. **Quote integrity engine** — detects price changes, removed products or
   variations, SKU changes, quantity-rule violations, expired offers and more
   before anything becomes an order.
7. **Quote-to-order bridge** — the buyer accepts the approved revision; the
   plugin creates the WooCommerce order with the negotiated line prices,
   idempotently (concurrent or duplicate submissions cannot create two orders).

## Requirements

- WordPress 6.4 or newer
- WooCommerce 8.0 or newer (High-Performance Order Storage compatible)
- PHP 8.1 or newer (8.2+ recommended), with `mbstring` and `dom`
- No Node.js, no Composer, no paid third-party service required at runtime

Executed test status for specific version combinations is recorded honestly in
`docs/testing/COMPATIBILITY_MATRIX.md` and
`docs/testing/ACCEPTANCE_LEDGER.md`.

## Installation (from the release ZIP)

1. In WordPress admin, go to **Plugins → Add New → Upload Plugin**.
2. Upload `tradesheet-studio-v1.0.0.zip` and click **Install Now**.
3. Activate the plugin. WooCommerce must be installed and active.
4. Open **WooCommerce → TradeSheet Studio** to configure your company identity
   and create your first price book.

## Building from source

The `plugin/tradesheet-studio/` directory contains the installable plugin.
Compiled admin assets and the bundled PDF renderer are committed so the plugin
installs without build tooling. To rebuild:

```bash
cd frontend && npm install && npm run build      # compiles React admin app
composer install --no-dev --prefer-dist           # refresh bundled PHP deps
./scripts/build-release.sh 1.0.0                  # produces release ZIPs
```

See `docs/installation/INSTALLATION.md` and `docs/development/` for details.

## Documentation

- Product guide: `docs/product/USER_GUIDE.md`
- Architecture: `docs/architecture/ARCHITECTURE.md`
- Security: `docs/security/THREAT_MODEL.md`
- Compliance readiness: `docs/compliance/COMMERCIAL-LEGAL-READINESS.md`

## License

Original code is licensed **GPL-2.0-or-later** (see `LICENSE`). Third-party
components and their licenses are listed in `THIRD-PARTY-NOTICES.md`.
Purchasing the product buys convenient packaging, documentation and support —
it does not remove the freedoms the included licenses grant you.

## Author

Aditya — <https://dev-aditya.com>

TradeSheet Studio is an independent commercial product. It is not affiliated
with, endorsed by, or sponsored by WordPress or WooCommerce.
