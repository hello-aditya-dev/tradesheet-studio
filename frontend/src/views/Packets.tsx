import type { MouseEvent as MouseEventT, ReactElement } from '../types/wp';
import { useState } from '../wp';
import { currencySymbol, errorMessage } from '../api';
import { formatDate, formatDateTime } from '../format';
import { useDebounced, useResource } from '../hooks';
import { packetAllows, PACKET_STATUSES, type ViewProps } from '../nav';
import { doDeleteDraft, doDownloadDoc, doRevise, doShareLink, doTransition } from '../packetActions';
import { toast } from '../components/toast';
import {
        Button, Card, EmptyState, ErrorBanner, Pagination, PacketStatusBadge, PageHeader, RowMenu, SearchInput, Select, Skeleton,
} from '../components/ui';
import type { PacketSummary } from '../types/api';

const PER_PAGE = 20;

interface PacketsResponse {
        items: PacketSummary[];
}

export function PacketsView({ go }: ViewProps): ReactElement {
        const [status, setStatus] = useState('');
        const [search, setSearch] = useState('');
        const [page, setPage] = useState(1);
        const debouncedSearch = useDebounced(search, 350);

        const path =
                `packets?per_page=${PER_PAGE}&page=${page}` +
                (status !== '' ? `&status=${encodeURIComponent(status)}` : '') +
                (debouncedSearch !== '' ? `&search=${encodeURIComponent(debouncedSearch)}` : '');
        const { data, loading, loadingFresh, error, reload } = useResource<PacketsResponse>(path);

        const items = data?.items ?? [];

        const refreshAll = (): void => {
                reload();
        };

        const runAction = (fn: () => Promise<void>): void => {
                fn().catch((err: unknown) => toast.error(errorMessage(err)));
        };

        return (
                <div className="tss-view">
                        <PageHeader
                                title="Sales packets"
                                subtitle="DealPackets across the whole lifecycle — drafts, issued versions, quotes and terminal states"
                                actions={
                                        <span className="tss-pagehead__actions">
                                                <Button variant="primary" onClick={() => go({ name: 'create' })}>Create packet</Button>
                                                <Button variant="ghost" onClick={reload} ariaLabel="Refresh list">Refresh</Button>
                                        </span>
                                }
                        />

                        <Card flush className="tss-filters-card">
                                <div className="tss-filters">
                                        <Select value={status} onChange={(v) => { setStatus(v); setPage(1); }} ariaLabel="Filter by status">
                                                <option value="">All statuses</option>
                                                {PACKET_STATUSES.map((s) => (
                                                        <option key={s} value={s}>{s.replace(/_/g, ' ')}</option>
                                                ))}
                                        </Select>
                                        <SearchInput
                                                value={search}
                                                onChange={(v) => { setSearch(v); setPage(1); }}
                                                placeholder="Search ref or title…"
                                                ariaLabel="Search packets by reference or title"
                                        />
                                </div>
                        </Card>

                        <ErrorBanner message={error} onRetry={reload} />

                        <Card flush>
                                {loading && !data ? (
                                        <div className="tss-card__pad"><Skeleton rows={6} /></div>
                                ) : items.length === 0 ? (
                                        <EmptyState
                                                title={error ? 'Could not load packets' : 'No packets match'}
                                                hint={
                                                        error
                                                                ? 'Retry once the connection to the REST API recovers.'
                                                                : status !== '' || debouncedSearch !== ''
                                                                        ? 'Try clearing the status filter or search term.'
                                                                        : 'Create your first packet: pick a buyer, add products, set terms and publish an immutable version.'
                                                }
                                        >
                                                <Button variant="primary" onClick={() => go({ name: 'create' })}>Create packet</Button>
                                        </EmptyState>
                                ) : (
                                        <div className={'tss-table-wrap' + (loadingFresh ? ' is-refreshing' : '')}>
                                                <table className="tss-table">
                                                        <thead>
                                                                <tr>
                                                                        <th>Ref</th>
                                                                        <th>Title</th>
                                                                        <th>Buyer</th>
                                                                        <th>Status</th>
                                                                        <th>Version</th>
                                                                        <th>Valid until</th>
                                                                        <th>Updated</th>
                                                                        <th aria-label="Row actions" />
                                                                </tr>
                                                        </thead>
                                                        <tbody>
                                                                {items.map((packet) => (
                                                                        <tr
                                                                                key={packet.id}
                                                                                className="tss-table__row-link"
                                                                                onClick={() => go({ name: 'packet', id: packet.id })}
                                                                        >
                                                                                <td className="tss-mono">{packet.ref}</td>
                                                                                <td className="tss-cell-title">{packet.title}</td>
                                                                                <td>
                                                                                        <span className="tss-cell-strong">{packet.customer_name || `#${packet.customer_id}`}</span>
                                                                                        {packet.customer_email ? <span className="tss-cell-sub">{packet.customer_email}</span> : null}
                                                                                </td>
                                                                                <td><PacketStatusBadge status={packet.status} /></td>
                                                                                <td>
                                                                                        {packet.current_version_id
                                                                                                ? <span className="tss-badge tss-badge--teal" title={`Version row #${packet.current_version_id}`}>issued</span>
                                                                                                : <span className="tss-dim">—</span>}
                                                                                </td>
                                                                                <td className="tss-dim">{formatDate(packet.valid_until)}</td>
                                                                                <td className="tss-dim">{formatDateTime(packet.updated_at)}</td>
                                                                                <td onClick={(e: MouseEventT<HTMLTableCellElement>) => e.stopPropagation()}>
                                                                                        <RowMenu
                                                                                                ariaLabel={`Actions for ${packet.ref}`}
                                                                                                items={[
                                                                                                        { label: 'View details', onClick: () => go({ name: 'packet', id: packet.id }) },
                                                                                                        { label: 'Share link', onClick: () => runAction(() => doShareLink(packet.id)) },
                                                                                                        {
                                                                                                                label: 'Download PDF',
                                                                                                                disabled: !packet.current_version_id,
                                                                                                                onClick: () => runAction(() => doDownloadDoc(packet.id, 'pdf', packet.ref)),
                                                                                                        },
                                                                                                        {
                                                                                                                label: 'Download CSV',
                                                                                                                disabled: !packet.current_version_id,
                                                                                                                onClick: () => runAction(() => doDownloadDoc(packet.id, 'csv', packet.ref)),
                                                                                                        },
                                                                                                        {
                                                                                                                label: 'Download ZIP',
                                                                                                                disabled: !packet.current_version_id,
                                                                                                                onClick: () => runAction(() => doDownloadDoc(packet.id, 'zip', packet.ref)),
                                                                                                        },
                                                                                                        { label: 'Revise…', divider: true, disabled: !packetAllows(packet.status, 'revise'), onClick: () => runAction(async () => { if (await doRevise(packet.id)) refreshAll(); }) },
                                                                                                        { label: 'Revoke…', danger: true, disabled: !packetAllows(packet.status, 'revoke'), onClick: () => runAction(async () => { if (await doTransition(packet.id, 'revoke')) refreshAll(); }) },
                                                                                                        { label: 'Expire…', danger: true, disabled: !packetAllows(packet.status, 'expire'), onClick: () => runAction(async () => { if (await doTransition(packet.id, 'expire')) refreshAll(); }) },
                                                                                                        { label: 'Archive…', disabled: !packetAllows(packet.status, 'archive'), onClick: () => runAction(async () => { if (await doTransition(packet.id, 'archive')) refreshAll(); }) },
                                                                                                        { label: 'Cancel…', danger: true, disabled: !packetAllows(packet.status, 'cancel'), onClick: () => runAction(async () => { if (await doTransition(packet.id, 'cancel')) refreshAll(); }) },
                                                                                                        { label: 'Delete draft…', divider: true, danger: true, disabled: packet.status !== 'draft', onClick: () => runAction(async () => { if (await doDeleteDraft(packet.id)) refreshAll(); }) },
                                                                                                ]}
                                                                                        />
                                                                                </td>
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
                                                        label="packets"
                                                />
                                        </div>
                                ) : null}
                        </Card>
                        <p className="tss-note">
                                Downloads use the current version's stored documents; the CSV order sheet and ZIP bundle are generated
                                alongside the PDF at publish time. Currency context: {currencySymbol() || '—'}.
                        </p>
                </div>
        );
}

