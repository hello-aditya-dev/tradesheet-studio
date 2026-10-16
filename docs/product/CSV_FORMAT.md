# CSV Order Sheet Format

The CSV order sheet is the offline, spreadsheet-friendly half of a
DealPacket: the merchant publishes it (generated automatically with the PDF
at publish time), the buyer fills quantities and uploads it back.

- Producing code: `src/Documents/CsvOrderSheetService.php`
- Download: buyer portal buttons or merchant
  `GET /packets/{id}/download?type=csv`
- Upload: buyer portal → *Upload CSV* (preview → confirm)

## Structure

### Meta rows

The first lines are human-readable comments starting with `#`. The parser
skips any row whose first cell starts with `#`:

```csv
# TradeSheet Studio Order Sheet
# Packet TSS-P-AB12 "Festive Season Electricals Offer" | Version 2 | Issued 2026-10-16 09:12:33 | Valid until 2026-11-15 23:59:59 | Currency INR | Prices tax-exclusive
# Enter quantities in the Qty column (respect Min and Step), save as CSV and upload it in your order portal.
```

The second row records the packet reference, version, issue and validity
timestamps, currency and whether prices are tax-inclusive or tax-exclusive
(from the store's tax settings at publish time).

### Header row

```csv
RowID,SKU,Product,Variation,UnitPrice,Qty,MinQty,Step,QtyBreaks
```

Column matching on upload is **case-insensitive by header name** (`rowid`,
`qty`, `sku`, `product` are the recognized columns), so reordering columns
is tolerated; the four recognized columns must be present.

### Data rows

```csv
L17,VW-LED-HB150,LED High Bay 150W 5000K IP65,"Colour: Graphite · Beam: 110°",4450.00,,2,2,"10+: 4180.00 | 50+: 3950.00"
L18,VW-LED-TP36,LED Triproof Batten 36W,,1240.00,,1,1,20+: 1150.00
L19,VW-DB-8WAY,Distribution Board 8-Way TPN IP65,,3890.00,,1,1,
```

| Column | Meaning | Used on upload? |
|---|---|---|
| `RowID` | `L` + packet line id — the **only** row identity the server trusts | Yes (required) |
| `SKU` | Snapshot SKU (informational) | Warning only on mismatch |
| `Product` | Snapshot product name (informational) | Warning only on mismatch |
| `Variation` | Variation summary (informational) | No |
| `UnitPrice` | Snapshot unit price, 2-decimal presentation | **No — never used** (prices come from the server-side snapshot) |
| `Qty` | The quantity the buyer orders — **the only buyer input honored** | Yes |
| `MinQty` / `Step` | Quantity rules copied from the snapshot | No (server re-validates from its own data) |
| `QtyBreaks` | Human-readable tiers, `10+: 4180.00` joined with `\|` | No |

Empty `Qty` = line skipped. Quantities must be plain non-negative integers
(`^\d{1,6}$`).

## Validation rules on upload

File-level:

| Rule | Effect |
|---|---|
| Size between 12 bytes and **2 MiB** (2,097,152 bytes) | Rejected otherwise |
| A header row containing a `RowID` column | Else `HEADER_MISSING` |
| At most **2000** order rows | Else `TOO_MANY_ROWS` |
| At least one order row | Else `NO_ROWS` |
| UTF-8 BOM tolerated (stripped) | — |

Row-level errors (the upload is rejected, listing each):

| Code | Condition |
|---|---|
| `ROW_ID_EMPTY` | A data row is missing its RowID |
| `UNKNOWN_ROW` | RowID does not exist in **this packet version** |
| `DUPLICATE_ROW` | Same RowID appears twice |
| `QTY_INVALID` | Qty is not a plain integer (formulas, text, negatives, decimals) |
| `QTY_BELOW_MIN` | Qty > 0 but below the version's minimum |
| `QTY_STEP_MISMATCH` | Qty not a multiple of the version's step |

Row-level warnings (accepted; packet values win, warnings displayed):

| Code | Condition |
|---|---|
| `SKU_CHANGED` | Uploaded SKU differs from the snapshot SKU |
| `PRODUCT_NAME_CHANGED` | Uploaded product name differs from the snapshot |

Every accepted row is then re-validated inside `QuoteRepository::createRequest`
(line ownership, min/step, qty-break re-application) before a quotation is
created — the uploaded file never feeds prices or row identity into the
quote beyond the quantity map.

## Formula-injection sanitization (both directions)

- **Export:** every dynamic cell passes through `Utils::csvSafe()`: values
  beginning with `=`, `+`, `-`, `@`, TAB or CR are prefixed with a single
  quote (`'`), so spreadsheet applications treat them as inert text — a
  product name like `=HYPERLINK(...)` cannot execute when the buyer opens
  the file. This behavior is unit-tested (formula-injection inertness).
- **Import:** parsing uses `fgetcsv` only — no formula interpretation, no
  eval. Spreadsheet-formula content in an uploaded file is inert data; the
  quantity regexp `^\d{1,6}$` additionally rejects anything that is not a
  plain integer.
- The buyer portal renders preview values as text (no spreadsheet engine
  involved anywhere).

## Example round-trip

1. Buyer downloads the sheet above, sets `Qty` for L17 to `10`, L18 to `20`,
   L19 to `1`, saves as CSV (UTF-8).
2. Upload → preview shows: L17 unit price **4180.00** (qty-break tier
   10+ applied server-side from the snapshot), L18 **1150.00** (tier 20+),
   L19 3890.00.
3. Confirm → quote request created with those quantities; the engine
   recomputes all prices from the immutable snapshot.

Tampering attempts (editing UnitPrice cells, swapping SKUs, inventing
RowIDs, duplicating rows) either produce warnings (packet values win) or
reject the upload — verified in the integration suite.
