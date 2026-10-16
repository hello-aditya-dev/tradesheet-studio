# Database Schema

12 tables, all prefixed `{$wpdb->prefix}tss_`, created by
`src/Database/Schema.php` through WordPress `dbDelta()` (additive
upgrades). WooCommerce's own tables are never modified; all access goes
through `$wpdb->prepare()`. Money columns are `DECIMAL(19,4)` and are
handled in PHP as canonical scale-4 decimal strings
(`src/Support/Money.php`).

Schema version: `2026-10-16-1` (option `tss_schema_version`, compare with
`TSS_SCHEMA_VERSION` in `tradesheet-studio.php`).

## Migration approach

- **Activation** (`Core\Installer::activate`): `dbDelta()` every CREATE
  statement, create the private storage dir, write options
  (`tss_schema_version`, `tss_version`, `tss_installed_at`), schedule
  `tss_hourly_sweep`.
- **Upgrades** (`Core\Installer::maybeUpgrade`, on admin requests): when
  the stored `tss_schema_version` differs from the code's, dbDelta runs
  again (additive: new columns/tables/indexes) and the option is updated.
- dbDelta rules are respected by the definitions (two spaces after
  `PRIMARY KEY`, lowercase `KEY` clauses, etc.).

## Uninstall

`uninstall.php` — data is **preserved by default**. Only when the merchant
enabled *Delete all TradeSheet Studio data on uninstall* does
`Schema::dropTables()` drop all 12 tables, the private document directory
get removed, options/transients/schedules deleted, and the
`_tss_pricebook_id` user meta cleared from all customers.

## Tables

### tss_pricebooks — wholesale price books

| Column | Type | Purpose |
|---|---|---|
| id | BIGINT UNSIGNED AI | PK |
| name | VARCHAR(191) | Book name |
| description | TEXT | Notes |
| status | VARCHAR(20) | `active` / `inactive` |
| currency | CHAR(3) | Informational currency tag |
| priority | SMALLINT UNSIGNED | Book-level priority |
| valid_from / valid_to | DATETIME NULL | Usability window |
| created_at / updated_at | DATETIME | Timestamps |

Indexes: `KEY status (status)`.

### tss_price_rules — pricing rules

| Column | Type | Purpose |
|---|---|---|
| id | BIGINT UNSIGNED AI | PK |
| pricebook_id | BIGINT UNSIGNED | Owning book |
| customer_id | BIGINT UNSIGNED | 0 = book-wide; >0 = customer-specific |
| product_id / variation_id | BIGINT UNSIGNED | 0/0 = book-wide; product/variation scope |
| price_override | DECIMAL(19,4) NULL | Fixed price (XOR with percent_off) |
| percent_off | DECIMAL(9,2) NULL | % off base (scale 2) |
| min_qty / qty_step | INT UNSIGNED | Quantity rules |
| qty_breaks | LONGTEXT | JSON tiers `[{"qty":10,"price":"…"}]` |
| priority | SMALLINT | Tie-break (desc) |
| valid_from / valid_to | DATETIME NULL | Rule window |
| created_at / updated_at | DATETIME | Timestamps |

Keys: `UNIQUE rule_scope (pricebook_id, customer_id, product_id,
variation_id)` — upsert replaces the rule for a scope;
`KEY product_lookup (product_id, variation_id)`; `KEY customer_lookup
(customer_id)`.

### tss_packets — DealPacket headers

| Column | Type | Purpose |
|---|---|---|
| id | BIGINT UNSIGNED AI | PK |
| ref | VARCHAR(32) | Human reference `TSS-P-XXXX` (base36, collision-checked) |
| customer_id | BIGINT UNSIGNED | Buyer (WooCommerce user) |
| title | VARCHAR(191) | Offer title |
| status | VARCHAR(20) | Packet state machine state |
| currency | CHAR(3) | Must equal store currency (v1) |
| pricebook_id | BIGINT NULL | Optional pinned book |
| current_version_id | BIGINT NULL | Latest published version |
| valid_until | DATETIME NULL | Offer validity |
| terms | LONGTEXT | JSON commercial terms |
| created_by / created_at / updated_at | — | Audit |

Keys: `UNIQUE packet_ref (ref)`; `KEY customer (customer_id)`;
`KEY status (status)`.

### tss_packet_versions — immutable snapshots

| Column | Type | Purpose |
|---|---|---|
| id | BIGINT UNSIGNED AI | PK |
| packet_id | BIGINT UNSIGNED | Parent packet |
| version_no | INT UNSIGNED | 1, 2, 3… |
| status | VARCHAR(20) | `issued` / `superseded` |
| snapshot | LONGTEXT | Frozen commercial payload (JSON) |
| content_checksum | CHAR(64) | SHA-256 of canonical snapshot content |
| issued_at / expires_at | DATETIME NULL | Version validity |
| issued_by | BIGINT NULL | Actor |

Keys: `UNIQUE packet_version (packet_id, version_no)`;
`KEY checksum (content_checksum)`.

### tss_packet_lines — draft lines AND frozen version lines

| Column | Type | Purpose |
|---|---|---|
| id | BIGINT UNSIGNED AI | PK (RowID source: `L{id}`) |
| packet_id | BIGINT UNSIGNED | Parent packet |
| version_id | BIGINT NULL | NULL = editable draft; set = frozen copy |
| product_id / variation_id | BIGINT UNSIGNED | Catalog reference |
| sku | VARCHAR(100) | Frozen SKU |
| name / variation_summary / description | TEXT | Frozen presentation |
| image_id | BIGINT NULL | Media attachment |
| qty_min / qty_step | INT UNSIGNED | Quantity rules |
| unit_price | DECIMAL(19,4) | Frozen price |
| price_source | VARCHAR(40) | `base` / `customer_rule` / `pricebook_rule` / `…_qty_break` / `packet_override` / `resolved` |
| qty_breaks | LONGTEXT | JSON tiers |
| sort_order / line_meta / created_at | — | Ordering, extra meta |

Keys: `KEY version_lines (version_id, sort_order)`;
`KEY packet_lines (packet_id, version_id)`;
`KEY product_lookup (product_id, variation_id)`.

### tss_documents — generated artifacts

| Column | Type | Purpose |
|---|---|---|
| id | BIGINT UNSIGNED AI | PK |
| version_id / packet_id | BIGINT UNSIGNED | Owner version/packet |
| type | VARCHAR(20) | `pdf` / `csv` / `zip` |
| template | VARCHAR(40) | PDF template slug (pdf rows) |
| file_path | VARCHAR(255) | Path **relative** to the private storage dir |
| file_hash | CHAR(64) | SHA-256 of the bytes |
| size_bytes | BIGINT UNSIGNED | Size |
| generated_at / generated_by | — | Audit |

Keys: `UNIQUE version_doc (version_id, type, template)` — regeneration
replaces the slot; `KEY packet_docs (packet_id)`.

### tss_buyer_tokens — hashed share tokens

| Column | Type | Purpose |
|---|---|---|
| id | BIGINT UNSIGNED AI | PK |
| packet_id | BIGINT UNSIGNED | Owning packet |
| token_hash | CHAR(64) | SHA-256 of the 64-hex token (raw never stored) |
| created_at / expires_at / revoked_at / last_used_at | DATETIME NULL | Lifecycle |
| use_count | BIGINT UNSIGNED | Audit counter |

Keys: `UNIQUE token_hash (token_hash)`; `KEY packet_tokens (packet_id)`.

### tss_quotes — quotation chain

| Column | Type | Purpose |
|---|---|---|
| id | BIGINT UNSIGNED AI | PK |
| ref | VARCHAR(32) | `TSS-Q-XXXX` |
| packet_id / version_id | BIGINT UNSIGNED | Identity integrity: which version was quoted |
| revision_no | INT UNSIGNED | 1, 2, 3… |
| parent_quote_id | BIGINT NULL | Revision chain |
| customer_id | BIGINT UNSIGNED | Buyer |
| status | VARCHAR(24) | Quote state machine state |
| requested_at / reviewed_at / approved_at / accepted_at / expires_at | DATETIME NULL | Lifecycle stamps |
| converted_order_id | BIGINT NULL | Resulting WooCommerce order |
| notes_buyer / notes_merchant | TEXT | Notes |
| discrepancy_report | LONGTEXT | JSON integrity report |
| created_at / updated_at | DATETIME | Timestamps |

Keys: `UNIQUE quote_ref (ref)`; `KEY packet (packet_id)`;
`KEY customer (customer_id)`; `KEY status (status)`;
`KEY version (version_id)`.

### tss_quote_lines — quote lines

| Column | Type | Purpose |
|---|---|---|
| id | BIGINT UNSIGNED AI | PK |
| quote_id | BIGINT UNSIGNED | Parent quote |
| packet_line_id | BIGINT UNSIGNED | Source snapshot line |
| product_id / variation_id | BIGINT UNSIGNED | Catalog reference |
| sku / name / variation_summary | — | Frozen copies |
| qty | INT UNSIGNED | Buyer quantity |
| unit_price | DECIMAL(19,4) | Negotiated/snapshot price |
| price_source | VARCHAR(40) | Provenance |
| line_status | VARCHAR(20) | `ok` etc. |
| merchant_note | VARCHAR(255) | Per-line note (revisions) |

Keys: `KEY quote_lines (quote_id)`; `KEY packet_line (packet_line_id)`.

### tss_order_links — idempotent conversion ledger

| Column | Type | Purpose |
|---|---|---|
| quote_id | BIGINT UNSIGNED | **PRIMARY KEY** — one conversion row per quote, ever |
| order_id | BIGINT NULL | Created WooCommerce order |
| conversion_key | CHAR(64) | Idempotency key (UNIQUE) |
| status | VARCHAR(20) | `converting` / `complete` |
| attempts | SMALLINT UNSIGNED | Retry count |
| error | LONGTEXT | Last error |
| created_at / completed_at | DATETIME | Timing |

### tss_events — append-only audit trail

| Column | Type | Purpose |
|---|---|---|
| id | BIGINT UNSIGNED AI | PK |
| object_type | VARCHAR(30) | `packet` / `quote` / `pricebook` / `document` / `settings` / `order`… |
| object_id | BIGINT UNSIGNED | Object id |
| action | VARCHAR(40) | e.g. `created`, `published`, `share_link_created`, `rule_saved` |
| actor_id | BIGINT NULL | User id (null for system/sweep) |
| details | LONGTEXT | JSON context |
| created_at | DATETIME | Timestamp |

Keys: `KEY object (object_type, object_id)`; `KEY created (created_at)`.

### tss_jobs — background jobs

| Column | Type | Purpose |
|---|---|---|
| id | BIGINT UNSIGNED AI | PK |
| type | VARCHAR(40) | Job type |
| payload | LONGTEXT | JSON |
| status | VARCHAR(20) | `queued` / `running` / `done` / `failed` |
| attempts / max_attempts | SMALLINT UNSIGNED | Retry policy |
| scheduled_at / started_at / finished_at | DATETIME NULL | Timing |
| error / checkpoint | LONGTEXT | Failure info / resume point |
| created_at / updated_at | DATETIME | Timestamps |

Keys: `KEY status (status)`; `KEY type_status (type, status)`.

## Options and user meta

| Key | Purpose |
|---|---|
| `tss_settings` | Merchant settings (autoloaded, centralized sanitization) |
| `tss_schema_version` | Schema migration marker |
| `tss_version` / `tss_installed_at` | Version bookkeeping |
| `tss_last_sweep_at` | Sweep heartbeat |
| `_tss_pricebook_id` (user meta) | Customer → price book assignment |
| transient `tss_activation_missing_woocommerce` | Activation notice |
| transients `tss_rl_{md5}` | Rate-limit buckets (`src/Security/RateLimiter.php`) |
