import type { ReactElement } from '../types/wp';
import { useState } from '../wp';
import { apiGet, apiPost, currencySymbol, errorMessage } from '../api';
import { breaksLabel, formatBytes, formatDate, formatDateTime, humanize, isPresent, shortChecksum } from '../format';
import { estimateTotal, money } from '../money';
import { useResource } from '../hooks';
import { packetAllows, type ViewProps } from '../nav';
import { doDeleteDraft, doDownloadDoc, doDownloadDocExplicit, doRevise, doShareLink, doTransition } from '../packetActions';
import { toast } from '../components/toast';
import {
        Badge, Button, Card, CopyField, EmptyState, ErrorBanner, MetaList, PacketStatusBadge, PageHeader, RowMenu, Skeleton,
} from '../components/ui';
import type { EventItem, PacketDetail, PriceBook, ShareInfo } from '../types/api';

interface EventsResponse {
        items: EventItem[];
}

interface PriceBooksResponse {
        items: PriceBook[];
}

function eventDetailsText(details: EventItem['details']): string {
        if (typeof details === 'string') {
                try {
                        const parsed: unknown = JSON.parse(details);
                        if (parsed && typeof parsed === 'object') {
                                return compactDetails(parsed as Record<string, unknown>);
                        }
                        return details;
                } catch {
                        return details;
                }
        }
        if (details && typeof details === 'object') {
                return compactDetails(details);
        }
        return '';
}

function compactDetails(details: Record<string, unknown>): string {
        return Object.entries(details)
                .filter(([, v]) => v !== null && v !== undefined && v !== '')
                .slice(0, 4)
                .map(([k, v]) => `${k}: ${typeof v === 'object' ? JSON.stringify(v) : String(v)}`)
                .join(' · ');
}

export function PacketDetailView({ go, id }: ViewProps & { id: number }): ReactElement {
        const detail = useResource<PacketDetail>(`packets/${id}`);
        const events = useResource<EventsResponse>(`packets/${id}/events`);
        const books = useResource<PriceBooksResponse>('pricebooks');
        const [busyKey, setBusyKey] = useState<string | null>(null);

        const data = detail.data;
        const packet = data?.packet;
        const symbol = currencySymbol();

        const act = (key: string, fn: () => Promise<void>): void => {
                if (busyKey !== null) {
                        return;
                }
                setBusyKey(key);
                fn()
                        .catch((err: unknown) => toast.error(errorMessage(err)))
                        .finally(() => setBusyKey(null));
        };

        const bookName = (bookId: number | null): string => {
                if (!bookId) {
                        return 'None';
                }
                const book = books.data?.items.find((b) => b.id === bookId);
                return book ? `${book.name} (#${book.id})` : `#${bookId}`;
        };

        if (detail.loading && !data) {
                return (
                        <div className="tss-view">
                                <PageHeader title="Packet" back={{ label: 'Sales packets', onClick: () => go({ name: 'packets' }) }} />
                                <Card><Skeleton rows={6} /></Card>
                        </div>
                );
        }

        if (detail.error || !data || !packet) {
                return (
                        <div className="tss-view">
                                <PageHeader title="Packet" back={{ label: 'Sales packets', onClick: () => go({ name: 'packets' }) }} />
                                <ErrorBanner message={detail.error ?? 'Packet not found.'} onRetry={detail.reload} />
                        </div>
                );
        }

        const hasVersion = packet.current_version_id !== null;
        const isDraft = packet.status === 'draft';
        const currentPdfTemplate = data.documents.find((d) => d.type === 'pdf')?.template ?? '';

        return (
                <div className="tss-view">
                        <PageHeader
                                title={<span><span className="tss-mono tss-ref">{packet.ref}</span> — {packet.title}</span>}
                                subtitle={
                                        <span className="tss-pagehead__statusline">
                                                <PacketStatusBadge status={packet.status} />
                                                {hasVersion && data.current_version
                                                        ? <span> · version {data.current_version.version_no} · checksum <span className="tss-mono">{shortChecksum(data.current_version.checksum, 10)}</span></span>
                                                        : null}
                                        </span>
                                }
                                back={{ label: 'Sales packets', onClick: () => go({ name: 'packets' }) }}
                                actions={
                                        <span className="tss-pagehead__actions">
                                                {isDraft ? (
                                                        <Button variant="primary" onClick={() => go({ name: 'create', resumeId: packet.id })}>
                                                                Resume composing
                                                        </Button>
                                                ) : null}
                                                <Button onClick={() => act('share', () => doShareLink(packet.id))} busy={busyKey === 'share'} disabled={!hasVersion}>
                                                        Share link
                                                </Button>
                                                <RowMenu
                                                        ariaLabel="Packet actions"
                                                        items={[
                                                                { label: 'Download PDF', disabled: !hasVersion, onClick: () => act('pdf', () => doDownloadDoc(packet.id, 'pdf', packet.ref)) },
                                                                { label: 'Download CSV', disabled: !hasVersion, onClick: () => act('csv', () => doDownloadDoc(packet.id, 'csv', packet.ref)) },
                                                                { label: 'Download ZIP', disabled: !hasVersion, onClick: () => act('zip', () => doDownloadDoc(packet.id, 'zip', packet.ref)) },
                                                                {
                                                                        label: 'Regenerate documents',
                                                                        divider: true,
                                                                        disabled: !hasVersion,
                                                                        onClick: () => act('regen', async () => {
                                                                                await apiPost(`packets/${packet.id}/documents`, currentPdfTemplate === '' ? {} : { template: currentPdfTemplate });
                                                                                toast.success('Documents regenerated for the current version.');
                                                                                detail.reload();
                                                                        }),
                                                                },
                                                                { label: 'Revise…', divider: true, disabled: !packetAllows(packet.status, 'revise'), onClick: () => act('revise', async () => { if (await doRevise(packet.id)) detail.reload(); }) },
                                                                { label: 'Revoke…', danger: true, disabled: !packetAllows(packet.status, 'revoke'), onClick: () => act('revoke', async () => { if (await doTransition(packet.id, 'revoke')) detail.reload(); }) },
                                                                { label: 'Expire…', danger: true, disabled: !packetAllows(packet.status, 'expire'), onClick: () => act('expire', async () => { if (await doTransition(packet.id, 'expire')) detail.reload(); }) },
                                                                { label: 'Archive…', disabled: !packetAllows(packet.status, 'archive'), onClick: () => act('archive', async () => { if (await doTransition(packet.id, 'archive')) detail.reload(); }) },
                                                                { label: 'Cancel…', danger: true, disabled: !packetAllows(packet.status, 'cancel'), onClick: () => act('cancel', async () => { if (await doTransition(packet.id, 'cancel')) detail.reload(); }) },
                                                                {
                                                                        label: 'Delete draft…',
                                                                        divider: true,
                                                                        danger: true,
                                                                        disabled: !isDraft,
                                                                        onClick: () => act('delete', async () => {
                                                                                if (await doDeleteDraft(packet.id)) {
                                                                                        go({ name: 'packets' });
                                                                                }
                                                                        }),
                                                                },
                                                        ]}
                                                />
                                        </span>
                                }
                        />

                        <ErrorBanner message={detail.error} onRetry={detail.reload} />

                        <Card title="Packet header" actions={<Button variant="ghost" size="sm" onClick={detail.reload}>Refresh</Button>}>
                                <MetaList
                                        rows={[
                                                { label: 'Buyer', value: `${packet.customer_name || `#${packet.customer_id}`}${packet.customer_email ? ` · ${packet.customer_email}` : ''}` },
                                                { label: 'Status', value: <PacketStatusBadge status={packet.status} /> },
                                                { label: 'Price book', value: bookName(packet.pricebook_id) },
                                                { label: 'Currency', value: packet.currency || `${window.TSS_ADMIN.currency} (store)` },
                                                { label: 'Valid until', value: formatDate(packet.valid_until) },
                                                { label: 'Created', value: formatDateTime(packet.created_at) },
                                                { label: 'Updated', value: formatDateTime(packet.updated_at) },
                                                {
                                                        label: 'Estimated value',
                                                        value: money(estimateTotal(data.lines.map((l) => ({ unit_price: l.unit_price, qty: l.qty_min }))), symbol),
                                                },
                                        ]}
                                />
                                {hasVersion ? (
                                        <CopyField
                                                label="Current version checksum"
                                                value={data.current_version?.checksum ?? ''}
                                        />
                                ) : null}
                        </Card>

                        {hasVersion ? (
                                <Card title="Share link" subtitle="Token-gated buyer portal URL (fresh token)">
                                        <ShareLinkBlock id={packet.id} />
                                </Card>
                        ) : (
                                <Card title="Share link">
                                        <EmptyState
                                                title="Not published yet"
                                                hint="Share links exist once an immutable version has been published."
                                        />
                                </Card>
                        )}

                        <Card title={`Lines (${data.lines.length})`} flush subtitle={isDraft ? 'Editable in the composer' : 'Frozen snapshot of the current version'}>
                                {data.lines.length === 0 ? (
                                        <EmptyState
                                                title="No product lines"
                                                hint={isDraft ? 'Resume composing this draft to add products.' : 'This version was published without lines.'}
                                        >
                                                {isDraft ? <Button variant="primary" onClick={() => go({ name: 'create', resumeId: packet.id })}>Resume composing</Button> : null}
                                        </EmptyState>
                                ) : (
                                        <div className="tss-table-wrap">
                                                <table className="tss-table">
                                                        <thead>
                                                                <tr>
                                                                        <th>Row</th>
                                                                        <th>SKU</th>
                                                                        <th>Product</th>
                                                                        <th>Qty min</th>
                                                                        <th>Step</th>
                                                                        <th>Unit price</th>
                                                                        <th>Source</th>
                                                                        <th>Qty breaks</th>
                                                                </tr>
                                                        </thead>
                                                        <tbody>
                                                                {data.lines.map((line) => (
                                                                        <tr key={line.id}>
                                                                                <td className="tss-mono">{line.row_id}</td>
                                                                                <td className="tss-mono">{line.sku || <span className="tss-dim">—</span>}</td>
                                                                                <td>
                                                                                        <span className="tss-cell-strong">{line.name}</span>
                                                                                        {line.variation_summary ? <span className="tss-cell-sub">{line.variation_summary}</span> : null}
                                                                                </td>
                                                                                <td>{line.qty_min}</td>
                                                                                <td>{line.qty_step}</td>
                                                                                <td className="tss-strong">{money(line.unit_price, symbol)}</td>
                                                                                <td><Badge tone={line.price_source === 'packet_override' ? 'amber' : line.price_source === 'resolved' ? 'teal' : 'neutral'}>{humanize(line.price_source)}</Badge></td>
                                                                                <td className="tss-dim">{breaksLabel(line.qty_breaks, symbol)}</td>
                                                                        </tr>
                                                                ))}
                                                        </tbody>
                                                </table>
                                        </div>
                                )}
                        </Card>

                        <Card title={`Versions (${data.versions.length})`} flush>
                                {data.versions.length === 0 ? (
                                        <EmptyState title="No versions" hint="Versions are created when a packet is published." />
                                ) : (
                                        <div className="tss-table-wrap">
                                                <table className="tss-table">
                                                        <thead>
                                                                <tr>
                                                                        <th>Version</th>
                                                                        <th>Status</th>
                                                                        <th>Checksum</th>
                                                                        <th>Issued</th>
                                                                        <th>Expires</th>
                                                                        <th>Issued by</th>
                                                                </tr>
                                                        </thead>
                                                        <tbody>
                                                                {data.versions.map((version) => (
                                                                        <tr key={version.id}>
                                                                                <td className="tss-strong">v{version.version_no}</td>
                                                                                <td>
                                                                                        <Badge tone={version.status === 'issued' ? 'green' : version.status === 'superseded' ? 'gray' : 'neutral'}>
                                                                                                {humanize(version.status)}
                                                                                        </Badge>
                                                                                </td>
                                                                                <td className="tss-mono" title={version.checksum}>{shortChecksum(version.checksum, 14)}</td>
                                                                                <td className="tss-dim">{formatDateTime(version.issued_at)}</td>
                                                                                <td className="tss-dim">{formatDate(version.expires_at)}</td>
                                                                                <td className="tss-dim">{version.issued_by ? `#${version.issued_by}` : '—'}</td>
                                                                        </tr>
                                                                ))}
                                                        </tbody>
                                                </table>
                                        </div>
                                )}
                        </Card>

                        <Card title={`Documents (${data.documents.length})`} flush subtitle="Stored for the current version; downloads stream through permission-checked routes">
                                {data.documents.length === 0 ? (
                                        <EmptyState title="No documents" hint={hasVersion ? 'Regenerate documents for the current version.' : 'Documents are generated at publish time.'} />
                                ) : (
                                        <div className="tss-table-wrap">
                                                <table className="tss-table">
                                                        <thead>
                                                                <tr>
                                                                        <th>Type</th>
                                                                        <th>Template</th>
                                                                        <th>Size</th>
                                                                        <th>Generated</th>
                                                                        <th aria-label="Download" />
                                                                </tr>
                                                        </thead>
                                                        <tbody>
                                                                {data.documents.map((doc) => (
                                                                        <tr key={doc.id}>
                                                                                <td className="tss-strong">{doc.type.toUpperCase()}</td>
                                                                                <td>{doc.template ? humanize(doc.template) : <span className="tss-dim">—</span>}</td>
                                                                                <td className="tss-dim">{formatBytes(doc.size_bytes)}</td>
                                                                                <td className="tss-dim">{formatDateTime(doc.generated_at)}</td>
                                                                                <td>
                                                                                        <Button
                                                                                                size="sm"
                                                                                                onClick={() => act(`doc-${doc.id}`, async () => {
                                                                                                        await doDownloadDocExplicit(packet.id, packet.ref, doc.type, doc.template);
                                                                                                })}
                                                                                                busy={busyKey === `doc-${doc.id}`}
                                                                                        >
                                                                                                Download
                                                                                        </Button>
                                                                                </td>
                                                                        </tr>
                                                                ))}
                                                        </tbody>
                                                </table>
                                        </div>
                                )}
                        </Card>

                        <Card title={`Quotes (${data.quotes.length})`} flush>
                                {data.quotes.length === 0 ? (
                                        <EmptyState title="No quote requests" hint="Quotes arrive when the buyer submits quantities from the portal." />
                                ) : (
                                        <div className="tss-table-wrap">
                                                <table className="tss-table">
                                                        <thead>
                                                                <tr>
                                                                        <th>Ref</th>
                                                                        <th>Revision</th>
                                                                        <th>Status</th>
                                                                        <th>Blocking</th>
                                                                        <th>Warnings</th>
                                                                        <th>Expires</th>
                                                                        <th aria-label="Open" />
                                                                </tr>
                                                        </thead>
                                                        <tbody>
                                                                {data.quotes.map((quote) => (
                                                                        <tr key={quote.id} className="tss-table__row-link" onClick={() => go({ name: 'quote', id: quote.id })}>
                                                                                <td className="tss-mono">{quote.ref}</td>
                                                                                <td>R{quote.revision_no}</td>
                                                                                <td><Badge tone={quote.status === 'ordered' || quote.status === 'accepted' || quote.status === 'approved' ? 'green' : quote.status === 'rejected' || quote.status === 'expired' ? 'red' : 'amber'}>{humanize(quote.status)}</Badge></td>
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
                        </Card>

                        <Card title="Terms" subtitle="Commercial terms captured with this packet">
                                {isPresent(data.terms.payment_terms) || isPresent(data.terms.delivery_terms) || isPresent(data.terms.lead_time) || isPresent(data.terms.notes) || isPresent(data.terms.min_order_value) ? (
                                        <MetaList
                                                rows={[
                                                        { label: 'Payment terms', value: data.terms.payment_terms ?? '—' },
                                                        { label: 'Delivery terms', value: data.terms.delivery_terms ?? '—' },
                                                        { label: 'Lead time', value: data.terms.lead_time ?? '—' },
                                                        { label: 'Minimum order value', value: isPresent(data.terms.min_order_value) ? money(data.terms.min_order_value, symbol) : '—' },
                                                        { label: 'Notes', value: data.terms.notes ?? '—' },
                                                ]}
                                        />
                                ) : (
                                        <EmptyState title="No terms recorded" hint="Add payment, delivery and lead-time terms while composing." />
                                )}
                        </Card>

                        <Card title="Events" flush subtitle="Audit trail for this packet">
                                {events.loading && !events.data ? (
                                        <div className="tss-card__pad"><Skeleton rows={4} /></div>
                                ) : events.error ? (
                                        <div className="tss-card__pad"><ErrorBanner message={events.error} onRetry={events.reload} /></div>
                                ) : (events.data?.items.length ?? 0) === 0 ? (
                                        <EmptyState title="No events" hint="Actions on this packet are recorded here." />
                                ) : (
                                        <ul className="tss-timeline tss-admin-scroll">
                                                {(events.data?.items ?? []).map((event) => (
                                                        <li key={event.id} className="tss-timeline__item">
                                                                <span className="tss-timeline__dot" aria-hidden="true" />
                                                                <div className="tss-timeline__body">
                                                                        <span className="tss-timeline__head">
                                                                                <strong>{humanize(event.action)}</strong>
                                                                                <span className="tss-dim"> · {formatDateTime(event.created_at)}</span>
                                                                                {event.actor_id ? <span className="tss-dim"> · by user #{event.actor_id}</span> : null}
                                                                        </span>
                                                                        {eventDetailsText(event.details) ? <span className="tss-timeline__details">{eventDetailsText(event.details)}</span> : null}
                                                                </div>
                                                        </li>
                                                ))}
                                        </ul>
                                )}
                        </Card>
                </div>
        );
}


function ShareLinkBlock({ id }: { id: number }): ReactElement {
        const [share, setShare] = useState<ShareInfo | null>(null);
        const [error, setError] = useState<string | null>(null);
        const [busy, setBusy] = useState(false);

        return (
                <div>
                        {share ? (
                                <div className="tss-shareblock">
                                        <CopyField label="Buyer portal URL" value={share.url} />
                                        <p className="tss-dim tss-note-inline">Token valid until {formatDateTime(share.expires_at)}.</p>
                                </div>
                        ) : (
                                <div className="tss-shareblock">
                                        <p className="tss-dim">Issue a token-gated link for the buyer portal (share tokens from publishing remain valid).</p>
                                        <Button
                                                variant="primary"
                                                busy={busy}
                                                onClick={() => {
                                                        setBusy(true);
                                                        setError(null);
                                                        apiGet<ShareInfo>(`packets/${id}/share`)
                                                                .then(setShare)
                                                                .catch((err: unknown) => setError(errorMessage(err)))
                                                                .finally(() => setBusy(false));
                                                }}
                                        >
                                                Create share link
                                        </Button>
                                </div>
                        )}
                        <ErrorBanner message={error} />
                </div>
        );
}
