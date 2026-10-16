import type { ChangeEvent as ChangeEventT, ReactElement } from '../types/wp';
import { useState } from '../wp';
import { apiPost, bootstrap, currencySymbol, errorMessage } from '../api';
import { formatDate, formatDateTime, humanize, shortChecksum } from '../format';
import { money as fmtMoney, mulQty } from '../money';
import { useResource } from '../hooks';
import { QUOTE_STATUSES, type ViewProps } from '../nav';
import { confirm } from '../components/confirm';
import { toast } from '../components/toast';
import {
        Badge, Button, Card, CopyField, EmptyState, ErrorBanner, Field, MetaList, Modal, PageHeader, Pagination,
        QuoteStatusBadge, Select, Skeleton, TextArea, TextInput,
} from '../components/ui';
import type { ConvertResult, QuoteDetail, QuoteLine, QuoteSummary } from '../types/api';

const PER_PAGE = 20;

interface QuotesResponse {
        items: QuoteSummary[];
}

/* ================================================================== List */

export function QuotesView({ go }: ViewProps): ReactElement {
        const [status, setStatus] = useState('');
        const [page, setPage] = useState(1);

        const path =
                `quotes?per_page=${PER_PAGE}&page=${page}` +
                (status !== '' ? `&status=${encodeURIComponent(status)}` : '');
        const { data, loading, loadingFresh, error, reload } = useResource<QuotesResponse>(path);
        const items = data?.items ?? [];

        return (
                <div className="tss-view">
                        <PageHeader
                                title="Quotes"
                                subtitle="Buyer quote requests across the review, revision and conversion lifecycle"
                                actions={<Button variant="ghost" onClick={reload} ariaLabel="Refresh quotes">Refresh</Button>}
                        />

                        <Card flush className="tss-filters-card">
                                <div className="tss-filters">
                                        <Select value={status} onChange={(v) => { setStatus(v); setPage(1); }} ariaLabel="Filter quotes by status">
                                                <option value="">All statuses</option>
                                                {QUOTE_STATUSES.map((s) => (
                                                        <option key={s} value={s}>{s.replace(/_/g, ' ')}</option>
                                                ))}
                                        </Select>
                                </div>
                        </Card>

                        <ErrorBanner message={error} onRetry={reload} />

                        <Card flush>
                                {loading && !data ? (
                                        <div className="tss-card__pad"><Skeleton rows={6} /></div>
                                ) : items.length === 0 ? (
                                        <EmptyState
                                                title={error ? 'Could not load quotes' : 'No quotes match'}
                                                hint={
                                                        error
                                                                ? 'Retry once the REST API recovers.'
                                                                : status !== ''
                                                                        ? 'Try a different status filter.'
                                                                        : 'Quote requests appear here once a buyer submits quantities from the token-gated portal.'
                                                }
                                        />
                                ) : (
                                        <div className={'tss-table-wrap' + (loadingFresh ? ' is-refreshing' : '')}>
                                                <table className="tss-table">
                                                        <thead>
                                                                <tr>
                                                                        <th>Ref</th>
                                                                        <th>Packet</th>
                                                                        <th>Revision</th>
                                                                        <th>Customer</th>
                                                                        <th>Status</th>
                                                                        <th>Blocking</th>
                                                                        <th>Warnings</th>
                                                                        <th>Expires</th>
                                                                        <th aria-label="Open" />
                                                                </tr>
                                                        </thead>
                                                        <tbody>
                                                                {items.map((quote) => (
                                                                        <tr key={quote.id} className="tss-table__row-link" onClick={() => go({ name: 'quote', id: quote.id })}>
                                                                                <td className="tss-mono">{quote.ref}</td>
                                                                                <td>
                                                                                        <span className="tss-mono">{quote.packet_ref}</span>
                                                                                        {quote.packet_title ? <span className="tss-cell-sub">{quote.packet_title}</span> : null}
                                                                                </td>
                                                                                <td>R{quote.revision_no}</td>
                                                                                <td>
                                                                                        <span className="tss-cell-strong">{quote.customer_name || `#${quote.customer_id}`}</span>
                                                                                </td>
                                                                                <td><QuoteStatusBadge status={quote.status} /></td>
                                                                                <td>{quote.blocking_count > 0 ? <Badge tone="red">{quote.blocking_count}</Badge> : <span className="tss-dim">0</span>}</td>
                                                                                <td>{quote.warning_count > 0 ? <Badge tone="amber">{quote.warning_count}</Badge> : <span className="tss-dim">0</span>}</td>
                                                                                <td className="tss-dim">{formatDate(quote.expires_at)}</td>
                                                                                <td><Button size="sm" onClick={() => go({ name: 'quote', id: quote.id })}>Open</Button></td>
                                                                        </tr>
                                                                ))}
                                                        </tbody>
                                                </table>
                                        </div>
                                )}
                                {data ? (
                                        <div className="tss-card__pad">
                                                <Pagination
                                                        page={page}
                                                        hasPrev={page > 1}
                                                        hasNext={items.length === PER_PAGE}
                                                        onPage={setPage}
                                                        label="quotes"
                                                />
                                        </div>
                                ) : null}
                        </Card>
                        <p className="tss-note">
                                The integrity engine compares every request against the immutable packet version (prices, quantities, availability,
                                tax context). Blocking findings must be resolved before a quote becomes an order.
                        </p>
                </div>
        );
}

/* ================================================================ Detail */

export function QuoteDetailView({ go, id }: ViewProps & { id: number }): ReactElement {
        const detail = useResource<QuoteDetail>(`quotes/${id}`);
        const [busy, setBusy] = useState(false);
        const [noteDialog, setNoteDialog] = useState<'approve' | 'reject' | null>(null);
        const [note, setNote] = useState('');
        const [revising, setRevising] = useState(false);
        const [convertResult, setConvertResult] = useState<ConvertResult | null>(null);

        const data = detail.data;
        const quote = data?.quote;
        const symbol = currencySymbol();

        const act = (fn: () => Promise<void>): void => {
                if (busy) {
                        return;
                }
                setBusy(true);
                fn()
                        .catch((err: unknown) => toast.error(errorMessage(err)))
                        .finally(() => setBusy(false));
        };

        const doReview = (): void => {
                act(async () => {
                        await apiPost(`quotes/${id}/review`);
                        toast.success('Quote marked in review.');
                        detail.reload();
                });
        };

        const doApprove = (): void => {
                act(async () => {
                        await apiPost(`quotes/${id}/approve`, { notes: note.trim() });
                        toast.success('Quote approved — the buyer can now accept it.');
                        setNoteDialog(null);
                        setNote('');
                        detail.reload();
                });
        };

        const doReject = (): void => {
                act(async () => {
                        await apiPost(`quotes/${id}/reject`, { notes: note.trim() });
                        toast.success('Quote rejected.');
                        setNoteDialog(null);
                        setNote('');
                        detail.reload();
                });
        };

        const doConvert = (): void => {
                confirm({
                        title: 'Convert this quote into a WooCommerce order?',
                        message:
                                'A WooCommerce order is created at the quoted (negotiated) line prices — never the current public prices. The conversion is idempotent: converting again returns the same order.',
                        confirmLabel: 'Create order',
                }).then((proceed) => {
                        if (!proceed) {
                                return;
                        }
                        act(async () => {
                                const result = await apiPost<ConvertResult>(`quotes/${id}/convert`);
                                if (result.pending) {
                                        toast.info(result.message ?? 'A conversion is already in progress — retry shortly.');
                                        return;
                                }
                                setConvertResult(result);
                                toast.success(result.already
                                        ? (result.message ?? 'Existing order returned.')
                                        : `Order #${result.order_id} created at quoted prices.`);
                                detail.reload();
                        });
                });
        };

        const doReviseSubmit = (changes: Record<string, unknown>[], revisionNotes: string): Promise<void> =>
                apiPost<{ quote: QuoteSummary }>(`quotes/${id}/revise`, { changes, notes: revisionNotes })
                        .then((res) => {
                                toast.success(`Revision ${res.quote.ref} (R${res.quote.revision_no}) issued — the buyer must accept it.`);
                                go({ name: 'quote', id: res.quote.id });
                        });

        if (detail.loading && !data) {
                return (
                        <div className="tss-view">
                                <PageHeader title="Quote" back={{ label: 'Quotes', onClick: () => go({ name: 'quotes' }) }} />
                                <Card><Skeleton rows={6} /></Card>
                        </div>
                );
        }

        if (detail.error || !data || !quote) {
                return (
                        <div className="tss-view">
                                <PageHeader title="Quote" back={{ label: 'Quotes', onClick: () => go({ name: 'quotes' }) }} />
                                <ErrorBanner message={detail.error ?? 'Quote not found.'} onRetry={detail.reload} />
                        </div>
                );
        }

        const canReview = quote.status === 'requested';
        const canApprove = quote.status === 'requested' || quote.status === 'in_review';
        const canReject = quote.status === 'requested' || quote.status === 'in_review' || quote.status === 'approved';
        const canRevise = quote.status === 'requested' || quote.status === 'in_review';
        const canConvert = quote.status === 'accepted';

        const orderAdminUrl = (orderId: number): string =>
                `${bootstrap().adminUrl}post.php?post=${orderId}&action=edit`;

        return (
                <div className="tss-view">
                        <PageHeader
                                title={<span><span className="tss-mono tss-ref">{quote.ref}</span> — R{quote.revision_no} · {quote.customer_name || `customer #${quote.customer_id}`}</span>}
                                subtitle={
                                        <span className="tss-pagehead__statusline">
                                                <QuoteStatusBadge status={quote.status} />
                                                {data.packet ? <span> · packet <span className="tss-mono">{data.packet.ref}</span></span> : null}
                                                {data.version ? <span> · version {data.version.version_no} · checksum <span className="tss-mono">{shortChecksum(data.version.checksum, 10)}</span></span> : null}
                                        </span>
                                }
                                back={{ label: 'Quotes', onClick: () => go({ name: 'quotes' }) }}
                                actions={
                                        <span className="tss-pagehead__actions">
                                                {data.packet ? (
                                                        <Button variant="ghost" onClick={() => go({ name: 'packet', id: data.packet?.id ?? 0 })}>Open packet</Button>
                                                ) : null}
                                                <Button variant="ghost" onClick={detail.reload} ariaLabel="Refresh quote">Refresh</Button>
                                        </span>
                                }
                        />

                        <ErrorBanner message={detail.error} onRetry={detail.reload} />

                        <Card title="Quote header">
                                <MetaList
                                        rows={[
                                                { label: 'Status', value: <QuoteStatusBadge status={quote.status} /> },
                                                { label: 'Revision', value: `R${quote.revision_no}${quote.parent_quote_id ? ` (parent #${quote.parent_quote_id})` : ''}` },
                                                { label: 'Customer', value: `${quote.customer_name || '—'} (#${quote.customer_id})` },
                                                { label: 'Packet', value: data.packet ? `${data.packet.ref} — ${data.packet.title}` : '—' },
                                                { label: 'Quoted total', value: <span className="tss-strong">{fmtMoney(data.total, symbol)}</span> },
                                                { label: 'Requested', value: formatDateTime(quote.requested_at) },
                                                { label: 'Reviewed', value: formatDateTime(quote.reviewed_at) },
                                                { label: 'Approved', value: formatDateTime(quote.approved_at) },
                                                { label: 'Accepted', value: formatDateTime(quote.accepted_at) },
                                                { label: 'Expires', value: formatDate(quote.expires_at) },
                                                { label: 'Buyer notes', value: quote.notes_buyer || '—' },
                                                { label: 'Merchant notes', value: quote.notes_merchant || '—' },
                                                {
                                                        label: 'Order',
                                                        value: quote.converted_order_id
                                                                ? <a className="tss-link" href={orderAdminUrl(quote.converted_order_id)} target="_blank" rel="noreferrer">WooCommerce order #{quote.converted_order_id}</a>
                                                                : '—',
                                                },
                                        ]}
                                />
                        </Card>

                        {convertResult && convertResult.order_id ? (
                                <Card title="Order created" subtitle={convertResult.already ? 'This quote already had an order — the existing one is shown' : 'Created at the quoted line prices'}>
                                        <div className="tss-convertresult">
                                                <p>
                                                        <a className="tss-link tss-strong" href={orderAdminUrl(convertResult.order_id)} target="_blank" rel="noreferrer">
                                                                Open WooCommerce order #{convertResult.order_id}
                                                        </a>
                                                </p>
                                                {convertResult.message ? <p className="tss-dim">{convertResult.message}</p> : null}
                                                {convertResult.pay_url ? (
                                                        <CopyField label="Customer payment link" value={convertResult.pay_url} />
                                                ) : null}
                                                <p className="tss-dim tss-note-inline">Taxes are computed by WooCommerce from the negotiated line prices.</p>
                                        </div>
                                </Card>
                        ) : null}

                        <Card
                                title={`Lines (${data.lines.length})`}
                                flush
                                subtitle={canRevise ? 'Frozen snapshot prices — use Revise to propose changes' : 'Prices from the immutable packet version or an explicit merchant revision'}
                        >
                                {data.lines.length === 0 ? (
                                        <EmptyState title="No lines" hint="This quote carries no product lines." />
                                ) : (
                                        <div className="tss-table-wrap">
                                                <table className="tss-table">
                                                        <thead>
                                                                <tr>
                                                                        <th>Row</th>
                                                                        <th>Product</th>
                                                                        <th>Qty</th>
                                                                        <th>Unit price</th>
                                                                        <th>Line total</th>
                                                                        <th>Source</th>
                                                                        <th>Merchant note</th>
                                                                </tr>
                                                        </thead>
                                                        <tbody>
                                                                {data.lines.map((line) => (
                                                                        <tr key={line.id}>
                                                                                <td className="tss-mono">{line.row_id}</td>
                                                                                <td>
                                                                                        <span className="tss-cell-strong">{line.name}</span>
                                                                                        {line.variation_summary ? <span className="tss-cell-sub">{line.variation_summary}</span> : null}
                                                                                        {line.sku ? <span className="tss-cell-sub tss-mono">{line.sku}</span> : null}
                                                                                </td>
                                                                                <td>{line.qty}</td>
                                                                                <td className="tss-strong">{fmtMoney(line.unit_price, symbol)}</td>
                                                                                <td className="tss-strong">{fmtMoney(line.line_total, symbol)}</td>
                                                                                <td><Badge tone={line.price_source === 'merchant_revision' ? 'amber' : line.price_source === 'resolved' ? 'teal' : 'neutral'}>{humanize(line.price_source)}</Badge></td>
                                                                                <td className="tss-dim">{line.merchant_note || '—'}</td>
                                                                        </tr>
                                                                ))}
                                                        </tbody>
                                                </table>
                                        </div>
                                )}
                                <div className="tss-card__pad tss-quotetotal">
                                        <span>Quoted total</span>
                                        <span className="tss-strong">{fmtMoney(data.total, symbol)}</span>
                                </div>
                        </Card>

                        <DiscrepancyCard report={data.report} />
                        <ChangesCard changes={data.changes} />

                        {canReview || canApprove || canReject || canRevise || canConvert ? (
                                <Card title="Merchant actions" subtitle="State machine gates what is possible at each stage">
                                        <div className="tss-actions">
                                                {canReview ? <Button variant="primary" busy={busy} onClick={doReview}>Mark in review</Button> : null}
                                                {canApprove ? <Button variant="primary" busy={busy} onClick={() => { setNote(''); setNoteDialog('approve'); }}>Approve…</Button> : null}
                                                {canReject ? <Button variant="danger" busy={busy} onClick={() => { setNote(''); setNoteDialog('reject'); }}>Reject…</Button> : null}
                                                {canRevise ? (
                                                        <Button variant="amber" busy={busy} onClick={() => setRevising((v) => !v)}>
                                                                {revising ? 'Close revision editor' : 'Revise terms…'}
                                                        </Button>
                                                ) : null}
                                                {canConvert ? <Button variant="primary" busy={busy} onClick={doConvert}>Convert to order…</Button> : null}
                                        </div>
                                        {quote.status === 'revised' || quote.status === 'approved' ? (
                                                <p className="tss-dim tss-note-inline">
                                                        Awaiting buyer decision — revised and approved quotations must be explicitly accepted by the buyer before an order can be created.
                                                </p>
                                        ) : null}
                                        {quote.status === 'accepted' ? (
                                                <p className="tss-dim tss-note-inline">
                                                        The buyer accepted these terms — converting creates the WooCommerce order at the quoted prices.
                                                </p>
                                        ) : null}
                                </Card>
                        ) : null}

                        {revising && canRevise ? (
                                <ReviseEditor
                                    lines={data.lines}
                                    busy={busy}
                                    symbol={symbol}
                                    close={() => setRevising(false)}
                                    submit={doReviseSubmit}
                                />
                        ) : null}

                        {noteDialog !== null ? (
                                <Modal
                                        title={noteDialog === 'approve' ? 'Approve this quote?' : 'Reject this quote?'}
                                        onClose={() => setNoteDialog(null)}
                                >
                                        <Field label="Merchant note (optional)" hint="Recorded on the quote and shown to the buyer.">
                                                <TextArea value={note} onChange={setNote} rows={4} placeholder="e.g. Honoured the original offer price despite the catalog change." autoFocus />
                                        </Field>
                                        <div className="tss-modal__footer">
                                                <Button onClick={() => setNoteDialog(null)}>Cancel</Button>
                                                <Button variant={noteDialog === 'approve' ? 'primary' : 'danger'} busy={busy} onClick={noteDialog === 'approve' ? doApprove : doReject}>
                                                        {noteDialog === 'approve' ? 'Approve quote' : 'Reject quote'}
                                                </Button>
                                        </div>
                                </Modal>
                        ) : null}
                </div>
        );
}

/* ---------------------------------------------------------- Integrity */

function DiscrepancyCard(props: { report: Partial<{ items: { type: string; severity: string; row_id: string; message: string }[]; blocking_count: number; warning_count: number; has_discrepancies: boolean; checked_at: string }> }): ReactElement {
        const { report } = props;
        const items = report.items ?? [];

        return (
                <Card
                        title={`Integrity report (${(report.blocking_count ?? 0)} blocking · ${(report.warning_count ?? 0)} warnings)`}
                        subtitle="Integrity engine comparison against the immutable packet version"
                        flush
                >
                        {!report.has_discrepancies && items.length === 0 ? (
                                <div className="tss-card__pad"><div className="tss-preflight__ok">No discrepancies — the quote matches the issued offer exactly.</div></div>
                        ) : items.length === 0 ? (
                                <EmptyState title="No report data" hint="The report is computed server-side when the request is submitted." />
                        ) : (
                                <div className="tss-card__pad tss-reportlist">
                                        {items.map((item, i) => (
                                                <div key={`${item.type}-${i}`} className={`tss-reportitem tss-reportitem--${item.severity === 'blocking' ? 'blocking' : 'warning'}`}>
                                                        <span className="tss-reportitem__sev">{item.severity === 'blocking' ? 'Blocking' : 'Warning'}</span>
                                                        <span className="tss-reportitem__text">
                                                                <strong>{humanize(item.type)}</strong>
                                                                {item.row_id ? <span className="tss-mono tss-dim"> · {item.row_id}</span> : null}
                                                                <span className="tss-reportitem__msg">{item.message}</span>
                                                        </span>
                                                </div>
                                        ))}
                                </div>
                        )}
                </Card>
        );
}

function ChangesCard(props: { changes: readonly { row_id: string; name: string; change: string; old_price?: string; new_price?: string; old_qty?: number; new_qty?: number }[] }): ReactElement {
        const { changes } = props;
        const symbol = currencySymbol();

        if (changes.length === 0) {
                return (
                        <Card title="Changes vs parent revision">
                                <EmptyState title="No tracked changes" hint="Revision diffs appear here when a merchant revision (R2+) modifies prices, quantities or line membership." />
                        </Card>
                );
        }

        return (
                <Card title={`Changes vs parent revision (${changes.length})`} flush>
                        <div className="tss-table-wrap">
                                <table className="tss-table">
                                        <thead>
                                                <tr>
                                                        <th>Row</th>
                                                        <th>Product</th>
                                                        <th>Change</th>
                                                        <th>Detail</th>
                                                </tr>
                                        </thead>
                                        <tbody>
                                                {changes.map((change, i) => {
                                                        const tone = change.change === 'added' ? 'green' : change.change === 'removed' ? 'red' : 'amber';
                                                        let detail = '';
                                                        if (change.change === 'price') {
                                                                detail = `${fmtMoney(change.old_price ?? '', symbol)} → ${fmtMoney(change.new_price ?? '', symbol)}`;
                                                        } else if (change.change === 'qty') {
                                                                detail = `${change.old_qty ?? '—'} → ${change.new_qty ?? '—'} units`;
                                                        } else if (change.change === 'added') {
                                                                detail = 'new line in this revision';
                                                        } else if (change.change === 'removed') {
                                                                detail = 'dropped from this revision';
                                                        }
                                                        return (
                                                                <tr key={`${change.row_id}-${i}`}>
                                                                        <td className="tss-mono">{change.row_id}</td>
                                                                        <td className="tss-cell-title">{change.name}</td>
                                                                        <td><Badge tone={tone}>{humanize(change.change)}</Badge></td>
                                                                        <td className="tss-dim">{detail}</td>
                                                                </tr>
                                                        );
                                                })}
                                        </tbody>
                                </table>
                        </div>
                </Card>
        );
}

/* ------------------------------------------------------------ Revision */

interface ReviseEdit {
        unitPrice: string;
        qty: string;
        remove: boolean;
        note: string;
}

function ReviseEditor(props: {
        lines: readonly QuoteLine[];
        busy: boolean;
        symbol: string;
        close: () => void;
        submit: (changes: Record<string, unknown>[], notes: string) => Promise<void>;
}): ReactElement {
        const { lines, busy, symbol, close, submit } = props;
        const [edits, setEdits] = useState<Record<number, ReviseEdit>>({});
        const [notes, setNotes] = useState('');
        const [busyLocal, setBusyLocal] = useState(false);

        const editFor = (line: QuoteLine): ReviseEdit =>
                edits[line.id] ?? {
                        unitPrice: line.unit_price,
                        qty: String(line.qty),
                        remove: false,
                        note: line.merchant_note ?? '',
                };

        const setEdit = (lineId: number, patch: Partial<ReviseEdit>): void => {
                const line = lines.find((l) => l.id === lineId);
                const current = edits[lineId] ?? {
                        unitPrice: line?.unit_price ?? '',
                        qty: String(line?.qty ?? 0),
                        remove: false,
                        note: line?.merchant_note ?? '',
                };
                setEdits((prev) => ({ ...prev, [lineId]: { ...current, ...patch } }));
        };

        const isDirty = (line: QuoteLine): boolean => {
                const edit = editFor(line);
                return edit.remove
                        || edit.unitPrice.trim() !== line.unit_price
                        || Number(edit.qty) !== line.qty
                        || edit.note !== (line.merchant_note ?? '');
        };

        const submitRevision = (): void => {
                const changes: Record<string, unknown>[] = [];
                for (const line of lines) {
                        if (!isDirty(line)) {
                                continue;
                        }
                        const edit = editFor(line);
                        const change: Record<string, unknown> = { quote_line_id: line.id };
                        if (edit.remove) {
                                change.remove = true;
                        } else {
                                if (edit.unitPrice.trim() === '' || Number.isNaN(Number(edit.unitPrice)) || Number(edit.unitPrice) < 0) {
                                        toast.error(`Line ${line.row_id}: enter a valid non-negative price.`);
                                        return;
                                }
                                if (edit.qty.trim() === '' || Number.isNaN(Number(edit.qty)) || Number(edit.qty) < 0) {
                                        toast.error(`Line ${line.row_id}: enter a valid quantity.`);
                                        return;
                                }
                                change.unit_price = edit.unitPrice.trim();
                                change.qty = Math.max(0, Math.floor(Number(edit.qty)));
                        }
                        if (edit.note.trim() !== '') {
                                change.note = edit.note.trim();
                        }
                        changes.push(change);
                }

                if (changes.length === 0) {
                        toast.error('No changes entered — adjust at least one line before issuing a revision.');
                        return;
                }

                const retained = lines.filter((line) => {
                        const edit = editFor(line);
                        return !edit.remove && Number(edit.qty) > 0;
                }).length;
                if (retained === 0) {
                        toast.error('A revision must retain at least one product line.');
                        return;
                }

                setBusyLocal(true);
                submit(changes, notes.trim())
                        .then(() => {
                                close();
                        })
                        .catch((err: unknown) => toast.error(errorMessage(err)))
                        .finally(() => setBusyLocal(false));
        };

        return (
                <Card
                        title="Issue a merchant revision"
                        subtitle="A revision is a NEW immutable quote row the buyer must explicitly accept — the current one becomes superseded"
                        actions={<Button size="sm" variant="ghost" onClick={close}>Close</Button>}
                        flush
                >
                        <div className="tss-card__pad">
                                <p className="tss-dim tss-note-inline">
                                        Adjust prices or quantities per line (quantities must respect the packet minimum and step), tick Remove to drop a
                                        line, and add per-line notes shown to the buyer. Lines you leave untouched keep their snapshot prices.
                                </p>
                        </div>
                        <div className="tss-table-wrap">
                                <table className="tss-table tss-table--lines">
                                        <thead>
                                                <tr>
                                                        <th>Row</th>
                                                        <th>Product</th>
                                                        <th>Unit price</th>
                                                        <th>Qty</th>
                                                        <th>Line total (new)</th>
                                                        <th>Note to buyer</th>
                                                        <th>Remove</th>
                                                </tr>
                                        </thead>
                                        <tbody>
                                                {lines.map((line) => {
                                                        const edit = editFor(line);
                                                        const dirty = isDirty(line);
                                                        const newQty = edit.remove ? 0 : Math.max(0, Number(edit.qty) || 0);
                                                        const newTotal = edit.remove ? '0.0000' : mulQty(edit.unitPrice, newQty);
                                                        return (
                                                                <tr key={line.id} className={dirty ? 'is-dirty' : undefined}>
                                                                        <td className="tss-mono">{line.row_id}</td>
                                                                        <td>
                                                                                <span className="tss-cell-strong">{line.name}</span>
                                                                                {line.variation_summary ? <span className="tss-cell-sub">{line.variation_summary}</span> : null}
                                                                        </td>
                                                                        <td className="tss-cell-narrow">
                                                                                <TextInput
                                                                                        value={edit.unitPrice}
                                                                                        onChange={(v) => setEdit(line.id, { unitPrice: v })}
                                                                                        disabled={edit.remove}
                                                                                        mono
                                                                                        ariaLabel={`New unit price for ${line.row_id}`}
                                                                                />
                                                                        </td>
                                                                        <td className="tss-cell-narrow">
                                                                                <TextInput
                                                                                        type="number"
                                                                                        value={edit.qty}
                                                                                        onChange={(v) => setEdit(line.id, { qty: v })}
                                                                                        disabled={edit.remove}
                                                                                        min={0}
                                                                                        ariaLabel={`New quantity for ${line.row_id}`}
                                                                                />
                                                                        </td>
                                                                        <td className="tss-dim">{edit.remove ? '—' : fmtMoney(newTotal, symbol)}</td>
                                                                        <td className="tss-cell-narrow">
                                                                                <TextInput
                                                                                        value={edit.note}
                                                                                        onChange={(v) => setEdit(line.id, { note: v })}
                                                                                        disabled={edit.remove}
                                                                                        placeholder="optional"
                                                                                        ariaLabel={`Note for ${line.row_id}`}
                                                                                />
                                                                        </td>
                                                                        <td className="tss-cell-narrow">
                                                                                <input
                                                                                        type="checkbox"
                                                                                        className="tss-check"
                                                                                        checked={edit.remove}
                                                                                        onChange={(e: ChangeEventT<HTMLInputElement>) => setEdit(line.id, { remove: e.target.checked })}
                                                                                        aria-label={`Remove line ${line.row_id}`}
                                                                                />
                                                                        </td>
                                                                </tr>
                                                        );
                                                })}
                                        </tbody>
                                </table>
                        </div>
                        <div className="tss-card__pad">
                                <Field label="Revision note to the buyer" hint="Explains why the terms changed; recorded on the new revision.">
                                        <TextArea value={notes} onChange={setNotes} rows={3} placeholder="e.g. Raw-material costs rose 12% since the offer was issued; we honour the old price for the first 100 units." />
                                </Field>
                                <div className="tss-wizard__actions">
                                        <Button variant="amber" busy={busy || busyLocal} onClick={submitRevision}>Issue revision</Button>
                                        <Button variant="ghost" onClick={close}>Cancel</Button>
                                </div>
                        </div>
                </Card>
        );
}
