import type { ReactElement } from '../types/wp';
import { useState } from '../wp';
import { apiDelete, apiPost, errorMessage } from '../api';
import { useDebounced, useResource } from '../hooks';
import { type ViewProps } from '../nav';
import { toast } from '../components/toast';
import {
        Badge, Button, Card, EmptyState, ErrorBanner, Pagination, PageHeader, SearchInput, Select, Skeleton,
} from '../components/ui';
import type { CustomersResponse, PriceBook } from '../types/api';

const PER_PAGE = 25;

interface BooksResponse {
        items: PriceBook[];
}

export function CustomersView(_props: ViewProps): ReactElement {
        const [search, setSearch] = useState('');
        const [page, setPage] = useState(1);
        const [busyId, setBusyId] = useState<number | null>(null);
        const debounced = useDebounced(search, 350);

        const books = useResource<BooksResponse>('pricebooks');
        const path =
                `customers?per_page=${PER_PAGE}&page=${page}` +
                (debounced !== '' ? `&search=${encodeURIComponent(debounced)}` : '');
        const { data, loading, loadingFresh, error, reload } = useResource<CustomersResponse>(path);

        const items = data?.items ?? [];
        const bookName = (bookId: number | null): string => {
                if (!bookId) {
                        return '';
                }
                const book = books.data?.items.find((b) => b.id === bookId);
                return book ? book.name : `#${bookId}`;
        };

        const assign = (customerId: number, bookId: number, name: string): void => {
                setBusyId(customerId);
                apiPost(`pricebooks/${bookId}/customers`, { customer_id: customerId })
                        .then(() => {
                                toast.success(`${name} now resolves prices from “${bookName(bookId)}”.`);
                                reload();
                        })
                        .catch((err: unknown) => toast.error(errorMessage(err)))
                        .finally(() => setBusyId(null));
        };

        const unassign = (customerId: number, bookId: number, name: string): void => {
                setBusyId(customerId);
                apiDelete(`pricebooks/${bookId}/customers/${customerId}`)
                        .then(() => {
                                toast.success(`${name} unassigned — packets use base prices again.`);
                                reload();
                        })
                        .catch((err: unknown) => toast.error(errorMessage(err)))
                        .finally(() => setBusyId(null));
        };

        return (
                <div className="tss-view">
                        <PageHeader
                                title="Customers"
                                subtitle="WooCommerce customer accounts and the price book each one resolves prices from"
                                actions={<Button variant="ghost" onClick={reload} ariaLabel="Refresh customers">Refresh</Button>}
                        />

                        <Card flush className="tss-filters-card">
                                <div className="tss-filters">
                                        <SearchInput
                                                value={search}
                                                onChange={(v) => { setSearch(v); setPage(1); }}
                                                placeholder="Search name, email or company…"
                                                ariaLabel="Search customers"
                                        />
                                </div>
                        </Card>

                        <ErrorBanner message={error} onRetry={reload} />
                        {books.error ? <ErrorBanner message={`Price books could not be loaded: ${books.error}`} onRetry={books.reload} /> : null}

                        <Card flush>
                                {loading && !data ? (
                                        <div className="tss-card__pad"><Skeleton rows={6} /></div>
                                ) : items.length === 0 ? (
                                        <EmptyState
                                                title={error ? 'Could not load customers' : 'No customers found'}
                                                hint={
                                                        error
                                                                ? 'Retry once the REST API recovers.'
                                                                : debounced !== ''
                                                                        ? 'Try a different search term.'
                                                                        : 'Customer accounts appear here once they exist in WooCommerce (role “customer”).'
                                                }
                                        />
                                ) : (
                                        <div className={'tss-table-wrap' + (loadingFresh ? ' is-refreshing' : '')}>
                                                <table className="tss-table">
                                                        <thead>
                                                                <tr>
                                                                        <th>Name</th>
                                                                        <th>Email</th>
                                                                        <th>Company</th>
                                                                        <th>Price book</th>
                                                                        <th aria-label="Assignment">Assign to book</th>
                                                                </tr>
                                                        </thead>
                                                        <tbody>
                                                                {items.map((customer) => (
                                                                        <CustomerRow
                                                                                key={customer.id}
                                                                                customer={customer}
                                                                                bookLabel={bookName(customer.pricebook_id)}
                                                                                books={books.data?.items ?? []}
                                                                                busy={busyId === customer.id}
                                                                                onAssign={(bookId) => assign(customer.id, bookId, customer.display_name)}
                                                                                onUnassign={() => customer.pricebook_id && unassign(customer.id, customer.pricebook_id, customer.display_name)}
                                                                        />
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
                                                        hasNext={page * PER_PAGE < data.total}
                                                        onPage={setPage}
                                                        total={data.total}
                                                        per_page={PER_PAGE}
                                                        label="customers"
                                                />
                                        </div>
                                ) : null}
                        </Card>
                        <p className="tss-note">
                                A customer resolves prices from exactly one price book (assignment is exclusive). Unassigned customers get WooCommerce
                                base prices in their packets.
                        </p>
                </div>
        );
}

function CustomerRow(props: {
        customer: CustomersResponse['items'][number];
        bookLabel: string;
        books: readonly PriceBook[];
        busy: boolean;
        onAssign: (bookId: number) => void;
        onUnassign: () => void;
}): ReactElement {
        const { customer, bookLabel, books, busy, onAssign, onUnassign } = props;
        const [selected, setSelected] = useState('');

        return (
                <tr>
                        <td className="tss-cell-strong">{customer.display_name}</td>
                        <td className="tss-dim">{customer.email || '—'}</td>
                        <td className="tss-dim">{customer.billing_company || '—'}</td>
                        <td>
                                {customer.pricebook_id
                                        ? <Badge tone="teal" title={`Book id ${customer.pricebook_id}`}>{bookLabel || `#${customer.pricebook_id}`}</Badge>
                                        : <span className="tss-dim">base prices</span>}
                        </td>
                        <td className="tss-cell-actions">
                                <span className="tss-assignctl">
                                        <Select
                                                value={selected}
                                                onChange={setSelected}
                                                disabled={busy || books.length === 0}
                                                ariaLabel={`Price book for ${customer.display_name}`}
                                                className="tss-select--sm"
                                        >
                                                <option value="">{books.length === 0 ? 'No books yet' : 'Choose book…'}</option>
                                                {books.map((book) => (
                                                        <option key={book.id} value={String(book.id)}>
                                                                {book.name}{book.status !== 'active' ? ' (inactive)' : ''}
                                                        </option>
                                                ))}
                                        </Select>
                                        {customer.pricebook_id ? (
                                                <Button size="sm" variant="danger" busy={busy} disabled={busy} onClick={onUnassign}>
                                                        Unassign
                                                </Button>
                                        ) : (
                                                <Button
                                                        size="sm"
                                                        variant="primary"
                                                        busy={busy}
                                                        disabled={busy || selected === ''}
                                                        onClick={() => onAssign(Number(selected))}
                                                >
                                                        Assign
                                                </Button>
                                        )}
                                </span>
                        </td>
                </tr>
        );
}
