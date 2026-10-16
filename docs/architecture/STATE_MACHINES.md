# State Machines

Two machines govern the lifecycle; both are enforced server-side and throw
`ValidationException` on illegal transitions.

- Packet machine: `src/Packets/PacketStateMachine.php`
- Quote machine: `src/Quotes/QuoteRepository.php` (TRANSITIONS constant)

## Packet state machine

```
 draft ──publish──► issued ──buyer request──► submitted ──review──► in_review ──approve──► approved ──accept──► accepted ──convert──► ordered
   │                   │                          │                     │                      │                    │
   │                   └──────────────┬───────────┴────────────────────┴──────────────────────┘                    │
   ▼                                  ▼                                                                         ▼
cancelled                    expired / revoked / cancelled                                                archived ◄── (from every terminal state)
```

Transition table (`from → allowed targets`):

| From | Allowed targets |
|---|---|
| `draft` | `issued`, `cancelled` |
| `issued` | `draft`, `submitted`, `expired`, `revoked`, `cancelled`, `archived` |
| `submitted` | `draft`, `in_review`, `expired`, `revoked`, `cancelled`, `archived` |
| `in_review` | `draft`, `approved`, `issued`, `expired`, `revoked`, `cancelled`, `archived` |
| `approved` | `draft`, `accepted`, `in_review`, `expired`, `revoked`, `cancelled`, `archived` |
| `accepted` | `ordered`, `expired`, `revoked`, `cancelled`, `archived` |
| `ordered` | `archived` |
| `expired` | `archived` |
| `revoked` | `archived` |
| `cancelled` | `archived` |
| `archived` | — (terminal sink) |

- **Live states** (portal + quote submissions allowed):
  `issued`, `submitted`, `in_review`, `approved`, `accepted`.
- **Supersession is version-level:** publishing version N+1 marks the
  previous `tss_packet_versions.status = 'superseded'`; issued versions are
  never rewritten.
- Returning to `draft` from the middle states is the revise path
  (`PacketSnapshotService::revise` seeds a fresh draft; publishing again
  creates the next version).
- Rejecting a quote returns the packet to `issued` (the offer remains
  standing for a new request).

## Quote state machine

```
requested ──► in_review ──► approved ──► accepted ──► ordered
    │             │             │
    │             └────► revised ──► accepted ──► ordered
    │                           │
    ├──► rejected (terminal)
    ├──► superseded (terminal — replaced by a revision)
    ├──► expired   (terminal)
    └──► cancelled (terminal)
```

Transition table:

| From | Allowed targets |
|---|---|
| `requested` | `in_review`, `approved`, `revised`, `rejected`, `superseded`, `cancelled`, `expired` |
| `in_review` | `approved`, `revised`, `rejected`, `superseded`, `cancelled`, `expired` |
| `revised` | `accepted`, `rejected`, `superseded`, `cancelled`, `expired` |
| `approved` | `accepted`, `rejected`, `superseded`, `cancelled`, `expired` |
| `accepted` | `ordered`, `cancelled`, `expired` |
| `ordered` / `rejected` / `superseded` / `expired` / `cancelled` | — (terminal) |

- **One active chain per packet:** a new request for the same packet is
  refused while any quote in the chain is
  `requested`/`in_review`/`revised`/`approved`/`accepted`.
- **Revisions are new rows** (`parent_quote_id`, `revision_no`); the parent
  becomes `superseded` and can never be accepted — the buyer must
  re-accept the newest revision.
- Quote line prices come exclusively from the immutable version snapshot or
  explicit merchant revision — never from the browser.

## Two-step transitions — why they exist

The packet machine only allows **single-hop** transitions (each hop is
individually validated and logged in `tss_events`). Business milestones,
however, span two hops, so the services *drive the machine* through each
legal hop in order:

1. **`submitted → in_review → approved`** — when a merchant approves a
   quote (`QuoteRepository::approve`), a packet still in `submitted` is
   first moved to `in_review` and then to `approved` (two setStatus calls,
   two audit events). Skipping directly `submitted → approved` is illegal
   by design: the review-start event must exist in the audit trail before
   the decision event, so the timeline always shows *who opened review*
   separately from *who approved*.

2. **`in_review → approved → accepted`** — buyer acceptance
   (`QuoteRepository::accept`) on a packet still sitting in `in_review`
   walks it through `approved` first, then `accepted`. Acceptance of an
   unapproved offer is never recorded as a single jump; the audit trail
   preserves the commercial fact that approval preceded acceptance.

The same pattern applies at the quote level: `requested` may go directly to
`approved` (a merchant may approve without an explicit review step — the
`in_review` state is optional there), but the packet-level mirror always
stays legal.

## Terminal semantics

| State | Portal | New quotes | Notes |
|---|---|---|---|
| `expired` | denied | refused | Set by sweep when `expires_at` passes, or manually |
| `revoked` | denied | refused | Merchant withdrawal; all buyer tokens revoked |
| `cancelled` | denied | refused | Abandoned pre-acceptance |
| `archived` | denied | refused | Terminal sink; history retained |
| `ordered` (packet) | allowed for reference | one chain only | Order exists; packet can still be archived |

Both machines are exercised exhaustively by the standalone unit suite
(`tests/unit/run.php`) and by the integration golden path.
