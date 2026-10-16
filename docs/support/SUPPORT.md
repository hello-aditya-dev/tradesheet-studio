# Support

## How to get help

| Channel | Use |
|---|---|
| Email: **hi.aditya.dev@gmail.com** | Bug reports, security issues (see `SECURITY.md` for disclosure etiquette), pre-sales questions |
| Product site: https://dev-aditya.com | Documentation pointers and announcements |
| Repository issues (for source customers) | Feature requests and reproducible bug reports |

Please use one thread per issue and include the information below — support
quality depends on it.

## What to include in every request

1. **Diagnostics output** — WooCommerce → TradeSheet Studio →
   Diagnostics: copy the full checks grid (name, ok, value) plus the
   **plugin version** (1.0.0) and **schema version** shown on that screen.
2. **Versions** — WordPress, WooCommerce, PHP, and whether HPOS is enabled
   (the diagnostics row states it).
3. **References** — packet reference (`TSS-P-XXXX`) and/or quote reference
   (`TSS-Q-XXXX`), with the revision number for quote issues.
4. **What happened vs what you expected** — exact steps; the error message
   text (do not paraphrase), and the timestamp.
5. **Relevant event history** — the packet detail's **Events** timeline
   around the problem time (copy the rows).
6. For buyer-portal issues: whether the buyer used the share link (token)
   or was logged in, the browser, and whether the portal showed a denial
   page.
7. For document issues: which template, and whether Diagnostics shows the
   PDF renderer and document-storage checks green.

Never send buyer tokens, passwords, or full database dumps. A share token
is a credential — if you suspect leakage, re-share the packet (this revokes
the old token) and say so in the report.

## Response expectations

| Severity | Target first response |
|---|---|
| Security reports | 72 hours (see `SECURITY.md`) |
| Broken core flow (publish/convert blocked) | 2 business days |
| Other bugs | 5 business days |
| How-to / configuration questions | best effort, 5 business days |

Times are in IST (UTC+5:30). Support covers the current release line
(1.0.x). Buyer-portal behavior on heavily customized WooCommerce setups may
require reproducing on a stock install first.

## Before you write

- Run through `docs/installation/TROUBLESHOOTING.md` — the most common
  causes (requirements notice, missing renderer, unwritable uploads,
  WP-Cron, permalinks) are solved there in minutes.
- Check `docs/testing/COMPATIBILITY_MATRIX.md` — if your exact
  WordPress/WooCommerce/PHP combination is marked NOT TESTED, say so; that
  changes triage.

## Refunds

Refund handling follows the channel you purchased through (marketplace
policy where applicable; otherwise the refund policy published at
purchase time on the product site). Include your purchase reference and a
short description of the incompatibility or defect.
