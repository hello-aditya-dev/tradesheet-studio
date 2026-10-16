import type { MouseEvent as MouseEventT, ReactElement } from '../types/wp';
import { useState } from '../wp';
import { apiDelete, apiGet, apiPost, currencySymbol, errorMessage } from '../api';
import { breaksLabel, dateInputValue, formatDate, humanize } from '../format';
import { money as fmtMoney } from '../money';
import { useDebounced, useResource } from '../hooks';
import { type View, type ViewProps } from '../nav';
import { confirm } from '../components/confirm';
import { toast } from '../components/toast';
import {
        Badge, Button, Card, EmptyState, ErrorBanner, Field, MetaList, PageHeader, RowMenu, SearchInput, Select,
        Skeleton, TextArea, TextInput,
} from '../components/ui';
import type { AssignedCustomer, CustomersResponse, PriceBook, PriceBookDetail, PriceResolution, QtyBreak } from '../types/api';

interface BooksResponse {
        items: PriceBook[];
}

function statusTone(status: string): 'green' | 'gray' | 'neutral' {
        if (status === 'active') {
                return 'green';
        }
        if (status === 'inactive') {
                return 'gray';
        }
        return 'neutral';
}

function validityLabel(book: PriceBook): string {
        const from = book.valid_from ? formatDate(book.valid_from) : '';
        const to = book.valid_to ? formatDate(book.valid_to) : '';
        if (from === '' && to === '') {
                return 'Always';
        }
        if (from !== '' && to !== '') {
                return `${from} → ${to}`;
        }
        return from !== '' ? `from ${from}` : `until ${to}`;
}

/* ================================================================== List */

export function PriceBooksView({ go }: ViewProps): ReactElement {
        const books = useResource<BooksResponse>('pricebooks');
        const [creating, setCreating] = useState(false);
        const [busy, setBusy] = useState(false);

        const items = books.data?.items ?? [];

        const duplicate = (book: PriceBook): void => {
                confirm({
                        title: `Duplicate "${book.name}"?`,
                        message: 'A copy is created with " (copy)" appended to the name, carrying the same rules and validity window but no customer assignments. Rules can then be adjusted independently.',
                        confirmLabel: 'Duplicate book',
                }).then((proceed) => {
                        if (!proceed) {
                                return;
                        }
                        setBusy(true);
                        apiPost<{ id: number }>(`pricebooks/${book.id}/duplicate`)
                                .then((res) => {
                                        toast.success('Price book duplicated.');
                                        go({ name: 'pricebook', id: res.id });
                                })
                                .catch((err: unknown) => toast.error(errorMessage(err)))
                                .finally(() => setBusy(false));
                });
        };

        const remove = (book: PriceBook): void => {
                confirm({
                        title: `Delete "${book.name}"?`,
                        message: 'The book and all its rules are removed permanently. Customers assigned to it fall back to WooCommerce base prices. Existing packet versions keep their frozen prices.',
                        confirmLabel: 'Delete book',
                        danger: true,
                }).then((proceed) => {
                        if (!proceed) {
                                return;
                        }
                        setBusy(true);
                        apiDelete(`pricebooks/${book.id}`)
                                .then(() => {
                                        toast.success('Price book deleted.');
                                        books.reload();
                                })
                                .catch((err: unknown) => toast.error(errorMessage(err)))
                                .finally(() => setBusy(false));
                });
        };

        return (
                <div className="tss-view">
                        <PageHeader
                                title="Price books"
                                subtitle="Customer-scoped pricing rules that feed packet line prices; higher priority wins within overlapping scopes"
                                actions={
                                        <span className="tss-pagehead__actions">
                                                <Button variant="primary" onClick={() => setCreating((v) => !v)}>
                                                        {creating ? 'Close form' : 'New price book'}
                                                </Button>
                                                <Button variant="ghost" onClick={books.reload} ariaLabel="Refresh price books">Refresh</Button>
                                        </span>
                                }
                        />

                        <ErrorBanner message={books.error} onRetry={books.reload} />

                        {creating ? (
                                <CreateBookCard
                                        busy={busy}
                                        onCreated={(id) => {
                                                setCreating(false);
                                                go({ name: 'pricebook', id });
                                        }}
                                />
                        ) : null}

                        <Card flush>
                                {books.loading && !books.data ? (
                                        <div className="tss-card__pad"><Skeleton rows={5} /></div>
                                ) : items.length === 0 ? (
                                        <EmptyState
                                                title={books.error ? 'Could not load price books' : 'No price books yet'}
                                                hint="Create a price book, add rules (fixed price or percentage off per customer/product scope) and assign customers to it."
                                        >
                                                <Button variant="primary" onClick={() => setCreating(true)}>New price book</Button>
                                        </EmptyState>
                                ) : (
                                        <div className="tss-table-wrap">
                                                <table className="tss-table">
                                                        <thead>
                                                                <tr>
                                                                        <th>Name</th>
                                                                        <th>Status</th>
                                                                        <th>Priority</th>
                                                                        <th>Valid window</th>
                                                                        <th>Rules</th>
                                                                        <th>Customers</th>
                                                                        <th aria-label="Row actions" />
                                                                </tr>
                                                        </thead>
                                                        <tbody>
                                                                {items.map((book) => (
                                                                        <tr key={book.id} className="tss-table__row-link" onClick={() => go({ name: 'pricebook', id: book.id })}>
                                                                                <td>
                                                                                        <span className="tss-cell-strong">{book.name}</span>
                                                                                        {book.description ? <span className="tss-cell-sub">{book.description}</span> : null}
                                                                                </td>
                                                                                <td><Badge tone={statusTone(book.status)}>{humanize(book.status)}</Badge></td>
                                                                                <td className="tss-dim">{book.priority}</td>
                                                                                <td className="tss-dim">{validityLabel(book)}</td>
                                                                                <td>{book.rule_count}</td>
                                                                                <td>{book.customer_count}</td>
                                                                                <td onClick={(e: MouseEventT<HTMLTableCellElement>) => { e.stopPropagation(); }}>
                                                                                        <RowMenu
                                                                                                ariaLabel={`Actions for ${book.name}`}
                                                                                                items={[
                                                                                                        { label: 'Open editor', onClick: () => go({ name: 'pricebook', id: book.id }) },
                                                                                                        { label: 'Duplicate…', disabled: busy, onClick: () => duplicate(book) },
                                                                                                        { label: 'Delete…', divider: true, danger: true, disabled: busy, onClick: () => remove(book) },
                                                                                                ]}
                                                                                        />
                                                                                </td>
                                                                        </tr>
                                                                ))}
                                                        </tbody>
                                                </table>
                                        </div>
                                )}
                        </Card>
                        <p className="tss-note">
                                A rule matches a (customer, product, variation) scope — 0 acts as a wildcard. Rule pricing is EITHER a fixed
                                price override OR a percentage off the base price, plus optional quantity breaks. Currency context: {currencySymbol() || '—'}.
                        </p>
                </div>
        );
}

function CreateBookCard(props: { busy: boolean; onCreated: (id: number) => void }): ReactElement {
        const { busy, onCreated } = props;
        const [name, setName] = useState('');
        const [description, setDescription] = useState('');
        const [currency, setCurrency] = useState(window.TSS_ADMIN.currency || '');
        const [priority, setPriority] = useState('0');
        const [validFrom, setValidFrom] = useState('');
        const [validTo, setValidTo] = useState('');
        const [status, setStatus] = useState('active');
        const [busyLocal, setBusyLocal] = useState(false);

        const submit = (): void => {
                if (name.trim() === '') {
                        toast.error('A price book requires a name.');
                        return;
                }
                setBusyLocal(true);
                apiPost<{ id: number }>('pricebooks', {
                        name: name.trim(),
                        description: description.trim(),
                        currency,
                        priority: Math.max(-99, Math.min(99, Number(priority) || 0)),
                        status,
                        ...(validFrom !== '' ? { valid_from: validFrom } : {}),
                        ...(validTo !== '' ? { valid_to: validTo } : {}),
                })
                        .then((res) => {
                                toast.success('Price book created — add rules next.');
                                onCreated(res.id);
                        })
                        .catch((err: unknown) => toast.error(errorMessage(err)))
                        .finally(() => setBusyLocal(false));
        };

        return (
                <Card title="New price book" subtitle="Books hold scoped rules; customers resolve through the book assigned to them">
                        <Field label="Name" required>
                                <TextInput value={name} onChange={setName} placeholder="e.g. Tier-1 Distributors 2025" autoFocus />
                        </Field>
                        <Field label="Description" hint="Internal note shown only in the admin.">
                                <TextArea value={description} onChange={setDescription} rows={2} placeholder="e.g. Net-30 partners, 8% off catalog" />
                        </Field>
                        <div className="tss-form-grid">
                                <Field label="Currency" hint="Store default when empty; informational for v1 (single-currency).">
                                        <TextInput value={currency} onChange={setCurrency} placeholder="e.g. INR" maxLength={3} mono ariaLabel="Price book currency" />
                                </Field>
                                <Field label="Priority" hint="Higher wins between overlapping books (-99…99).">
                                        <TextInput type="number" value={priority} onChange={setPriority} min={-99} max={99} />
                                </Field>
                                <Field label="Valid from">
                                        <TextInput type="date" value={validFrom} onChange={setValidFrom} />
                                </Field>
                                <Field label="Valid to">
                                        <TextInput type="date" value={validTo} onChange={setValidTo} />
                                </Field>
                        </div>
                        <Field label="Status">
                                <Select value={status} onChange={setStatus}>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                </Select>
                        </Field>
                        <div className="tss-wizard__actions">
                                <Button variant="primary" busy={busy || busyLocal} onClick={submit}>Create book</Button>
                        </div>
                </Card>
        );
}

/* ================================================================ Editor */

export function PriceBookDetailView({ go, id }: ViewProps & { id: number }): ReactElement {
        const detail = useResource<PriceBookDetail>(`pricebooks/${id}`);
        const [busy, setBusy] = useState(false);

        const data = detail.data;
        const book = data?.book;

        const act = (fn: () => Promise<void>): void => {
                if (busy) {
                        return;
                }
                setBusy(true);
                fn()
                        .catch((err: unknown) => toast.error(errorMessage(err)))
                        .finally(() => setBusy(false));
        };

        if (detail.loading && !data) {
                return (
                        <div className="tss-view">
                                <PageHeader title="Price book" back={{ label: 'Price books', onClick: () => go({ name: 'pricebooks' }) }} />
                                <Card><Skeleton rows={6} /></Card>
                        </div>
                );
        }

        if (detail.error || !data || !book) {
                return (
                        <div className="tss-view">
                                <PageHeader title="Price book" back={{ label: 'Price books', onClick: () => go({ name: 'pricebooks' }) }} />
                                <ErrorBanner message={detail.error ?? 'Price book not found.'} onRetry={detail.reload} />
                        </div>
                );
        }

        return (
                <div className="tss-view">
                        <PageHeader
                                title={book.name}
                                subtitle={<span><Badge tone={statusTone(book.status)}>{humanize(book.status)}</Badge> · priority {book.priority} · {validityLabel(book)}</span>}
                                back={{ label: 'Price books', onClick: () => go({ name: 'pricebooks' }) }}
                                actions={
                                        <span className="tss-pagehead__actions">
                                                <Button variant="ghost" onClick={detail.reload} ariaLabel="Refresh book">Refresh</Button>
                                        </span>
                                }
                        />

                        <ErrorBanner message={detail.error} onRetry={detail.reload} />

                        <BookMetaCard book={book} busy={busy} act={act} reload={detail.reload} go={go} />

                        <RulesCard bookId={book.id} rules={data.rules} busy={busy} reload={detail.reload} />

                        <BookCustomersCard bookId={book.id} customers={data.customers} busy={busy} reload={detail.reload} />

                        <PreviewCard bookId={book.id} />
                </div>
        );
}

function BookMetaCard(props: {
        book: PriceBook;
        busy: boolean;
        act: (fn: () => Promise<void>) => void;
        reload: () => void;
        go: (view: View) => void;
}): ReactElement {
        const { book, busy, act, reload, go } = props;
        const [editing, setEditing] = useState(false);
        const [name, setName] = useState(book.name);
        const [description, setDescription] = useState(book.description);
        const [status, setStatus] = useState(book.status);
        const [currency, setCurrency] = useState(book.currency);
        const [priority, setPriority] = useState(String(book.priority));
        const [validFrom, setValidFrom] = useState(dateInputValue(book.valid_from));
        const [validTo, setValidTo] = useState(dateInputValue(book.valid_to));

        if (!editing) {
                return (
                        <Card title="Book details" actions={<Button size="sm" onClick={() => setEditing(true)}>Edit details</Button>}>
                                <MetaList
                                        rows={[
                                                { label: 'Name', value: book.name },
                                                { label: 'Status', value: <Badge tone={statusTone(book.status)}>{humanize(book.status)}</Badge> },
                                                { label: 'Priority', value: String(book.priority) },
                                                { label: 'Currency', value: book.currency || '—' },
                                                { label: 'Valid window', value: validityLabel(book) },
                                                { label: 'Description', value: book.description || '—' },
                                                {
                                                        label: 'Counts',
                                                        value: `${book.rule_count} rule${book.rule_count === 1 ? '' : 's'} · ${book.customer_count} customer${book.customer_count === 1 ? '' : 's'}`,
                                                },
                                        ]}
                                />
                        </Card>
                );
        }

        const save = (): void => {
                if (name.trim() === '') {
                        toast.error('The name cannot be empty.');
                        return;
                }
                act(async () => {
                        await apiPost(`pricebooks/${book.id}`, {
                                name: name.trim(),
                                description: description.trim(),
                                status,
                                currency,
                                priority: Math.max(-99, Math.min(99, Number(priority) || 0)),
                                ...(validFrom !== '' ? { valid_from: validFrom } : {}),
                                ...(validTo !== '' ? { valid_to: validTo } : {}),
                        });
                        toast.success('Book details saved.');
                        setEditing(false);
                        reload();
                });
        };

        return (
                <Card
                        title="Edit book details"
                        subtitle="Validity dates can be set but not cleared through the API once assigned"
                        actions={<Button size="sm" variant="ghost" onClick={() => setEditing(false)}>Cancel</Button>}
                >
                        <Field label="Name" required>
                                <TextInput value={name} onChange={setName} />
                        </Field>
                        <Field label="Description">
                                <TextArea value={description} onChange={setDescription} rows={2} />
                        </Field>
                        <div className="tss-form-grid">
                                <Field label="Status">
                                        <Select value={status} onChange={setStatus}>
                                                <option value="active">Active</option>
                                                <option value="inactive">Inactive</option>
                                        </Select>
                                </Field>
                                <Field label="Currency">
                                        <TextInput value={currency} onChange={setCurrency} maxLength={3} mono ariaLabel="Book currency" />
                                </Field>
                                <Field label="Priority">
                                        <TextInput type="number" value={priority} onChange={setPriority} min={-99} max={99} />
                                </Field>
                        </div>
                        <div className="tss-form-grid">
                                <Field label="Valid from">
                                        <TextInput type="date" value={validFrom} onChange={setValidFrom} />
                                </Field>
                                <Field label="Valid to">
                                        <TextInput type="date" value={validTo} onChange={setValidTo} />
                                </Field>
                        </div>
                        <div className="tss-wizard__actions">
                                <Button variant="primary" busy={busy} onClick={save}>Save details</Button>
                                <Button variant="ghost" onClick={() => go({ name: 'pricebooks' })}>Back to list</Button>
                        </div>
                </Card>
        );
}

/* ---------------------------------------------------------------- Rules */

type RuleMode = 'override' | 'percent' | 'qty';

interface RuleDraft {
        customerId: string;
        productId: string;
        variationId: string;
        mode: RuleMode;
        priceOverride: string;
        percentOff: string;
        minQty: string;
        qtyStep: string;
        breaks: { qty: string; price: string }[];
        priority: string;
        validFrom: string;
        validTo: string;
        editingRuleId: number | null;
}

const EMPTY_RULE: RuleDraft = {
        customerId: '',
        productId: '',
        variationId: '',
        mode: 'override',
        priceOverride: '',
        percentOff: '',
        minQty: '1',
        qtyStep: '1',
        breaks: [],
        priority: '0',
        validFrom: '',
        validTo: '',
        editingRuleId: null,
};

function draftFromRule(rule: PriceBookDetail['rules'][number]): RuleDraft {
        const mode: RuleMode = rule.price_override !== null && rule.price_override !== undefined
                ? 'override'
                : rule.percent_off !== null && rule.percent_off !== undefined
                        ? 'percent'
                        : 'qty';
        return {
                customerId: rule.customer_id ? String(rule.customer_id) : '',
                productId: rule.product_id ? String(rule.product_id) : '',
                variationId: rule.variation_id ? String(rule.variation_id) : '',
                mode,
                priceOverride: mode === 'override' ? String(rule.price_override ?? '') : '',
                percentOff: mode === 'percent' ? String(rule.percent_off ?? '') : '',
                minQty: String(rule.min_qty || 1),
                qtyStep: String(rule.qty_step || 1),
                breaks: (rule.qty_breaks ?? []).map((b) => ({ qty: String(b.qty), price: b.price })),
                priority: String(rule.priority ?? 0),
                validFrom: dateInputValue(rule.valid_from),
                validTo: dateInputValue(rule.valid_to),
                editingRuleId: rule.id,
        };
}

function ruleScopeLabel(rule: PriceBookDetail['rules'][number]): ReactElement {
        const parts: ReactElement[] = [];
        parts.push(
                rule.customer_id
                        ? <Badge key="c" tone="teal">customer #{rule.customer_id}</Badge>
                        : <Badge key="c" tone="gray">all customers</Badge>,
        );
        parts.push(
                rule.product_id
                        ? <Badge key="p" tone="teal">product #{rule.product_id}</Badge>
                        : <Badge key="p" tone="gray">all products</Badge>,
        );
        if (rule.variation_id) {
                parts.push(<Badge key="v" tone="teal">variation #{rule.variation_id}</Badge>);
        }
        return <span className="tss-badges">{parts}</span>;
}

function rulePriceLabel(rule: PriceBookDetail['rules'][number], symbol: string): ReactElement {
        if (rule.price_override !== null && rule.price_override !== undefined && rule.price_override !== '') {
                return <span className="tss-strong">{fmtMoney(rule.price_override, symbol)}</span>;
        }
        if (rule.percent_off !== null && rule.percent_off !== undefined && rule.percent_off !== '') {
                return <span className="tss-strong">−{rule.percent_off}% off base</span>;
        }
        return <span className="tss-dim">qty rules / breaks only</span>;
}

function RulesCard(props: { bookId: number; rules: readonly PriceBookDetail['rules'][number][]; busy: boolean; reload: () => void }): ReactElement {
        const { bookId, rules, busy, reload } = props;
        const symbol = currencySymbol();
        const [draft, setDraft] = useState<RuleDraft | null>(null);
        const [busyLocal, setBusyLocal] = useState(false);

        const set = (patch: Partial<RuleDraft>): void => {
                setDraft((prev) => (prev === null ? prev : { ...prev, ...patch }));
        };

        const setBreak = (index: number, patch: Partial<{ qty: string; price: string }>): void => {
                setDraft((prev) => {
                        if (prev === null) {
                                return prev;
                        }
                        const breaks = prev.breaks.map((b, i) => (i === index ? { ...b, ...patch } : b));
                        return { ...prev, breaks };
                });
        };

        const removeRule = (ruleId: number): void => {
                setBusyLocal(true);
                apiDelete(`pricebooks/${bookId}/rules/${ruleId}`)
                        .then(() => {
                                toast.success('Rule deleted.');
                                reload();
                        })
                        .catch((err: unknown) => toast.error(errorMessage(err)))
                        .finally(() => setBusyLocal(false));
        };

        const submitRule = (): void => {
                if (draft === null) {
                        return;
                }
                if (draft.mode === 'override' && draft.priceOverride.trim() === '') {
                        toast.error('Enter the fixed price override, or switch the pricing mode.');
                        return;
                }
                if (draft.mode === 'percent' && draft.percentOff.trim() === '') {
                        toast.error('Enter the percentage discount, or switch the pricing mode.');
                        return;
                }
                if (draft.mode === 'percent' && (Number(draft.percentOff) < 0 || Number(draft.percentOff) > 100)) {
                        toast.error('The percentage discount must be between 0 and 100.');
                        return;
                }
                if (draft.variationId !== '' && Number(draft.variationId) > 0 && (draft.productId === '' || Number(draft.productId) < 1)) {
                        toast.error('A variation-scoped rule also needs a product id.');
                        return;
                }

                const breaks: QtyBreak[] = [];
                for (const b of draft.breaks) {
                        if (b.qty.trim() === '' && b.price.trim() === '') {
                                continue;
                        }
                        const qty = Number(b.qty);
                        if (!Number.isFinite(qty) || qty < 2 || qty > 100000) {
                                toast.error('Quantity break thresholds must be between 2 and 100000.');
                                return;
                        }
                        if (b.price.trim() === '' || Number.isNaN(Number(b.price))) {
                                toast.error('Each quantity break needs a price.');
                                return;
                        }
                        breaks.push({ qty, price: b.price.trim() });
                }

                const body: Record<string, unknown> = {
                        customer_id: Math.max(0, Number(draft.customerId) || 0),
                        product_id: Math.max(0, Number(draft.productId) || 0),
                        variation_id: Math.max(0, Number(draft.variationId) || 0),
                        min_qty: Math.max(1, Math.min(100000, Number(draft.minQty) || 1)),
                        qty_step: Math.max(1, Math.min(10000, Number(draft.qtyStep) || 1)),
                        priority: Math.max(-99, Math.min(99, Number(draft.priority) || 0)),
                        qty_breaks: breaks,
                };
                if (draft.mode === 'override') {
                        body.price_override = draft.priceOverride.trim();
                }
                if (draft.mode === 'percent') {
                        body.percent_off = draft.percentOff.trim();
                }
                if (draft.validFrom !== '') {
                        body.valid_from = draft.validFrom;
                }
                if (draft.validTo !== '') {
                        body.valid_to = draft.validTo;
                }
                if (draft.editingRuleId !== null) {
                        body.id = draft.editingRuleId;
                }

                setBusyLocal(true);
                apiPost(`pricebooks/${bookId}/rules`, body)
                        .then(() => {
                                toast.success(draft.editingRuleId !== null ? 'Rule updated (same scope replaced).' : 'Rule saved.');
                                setDraft(null);
                                reload();
                        })
                        .catch((err: unknown) => toast.error(errorMessage(err)))
                        .finally(() => setBusyLocal(false));
        };

        return (
                <Card
                        title={`Rules (${rules.length})`}
                        subtitle="One rule per (customer, product, variation) scope — saving the same scope replaces it. 0 = wildcard."
                        actions={
                                <Button size="sm" variant="primary" onClick={() => setDraft(draft === null ? EMPTY_RULE : null)}>
                                        {draft === null ? 'Add rule' : 'Close form'}
                                </Button>
                        }
                        flush
                >
                        {draft !== null ? (
                                <div className="tss-card__pad tss-ruleform">
                                        <h4 className="tss-subhead">{draft.editingRuleId !== null ? `Edit rule #${draft.editingRuleId}` : 'New rule'}</h4>
                                        <div className="tss-form-grid">
                                                <Field label="Customer id" hint="0 or empty = all customers.">
                                                        <TextInput type="number" value={draft.customerId} onChange={(v) => set({ customerId: v })} min={0} mono />
                                                </Field>
                                                <Field label="Product id" hint="0 or empty = all products.">
                                                        <TextInput type="number" value={draft.productId} onChange={(v) => set({ productId: v })} min={0} mono />
                                                </Field>
                                                <Field label="Variation id" hint="Optional; requires a product id.">
                                                        <TextInput type="number" value={draft.variationId} onChange={(v) => set({ variationId: v })} min={0} mono />
                                                </Field>
                                        </div>

                                        <Field label="Pricing mode" hint="A rule carries EITHER a fixed price OR a percentage off — never both.">
                                                <Select value={draft.mode} onChange={(v) => set({ mode: v as RuleMode })}>
                                                        <option value="override">Fixed price override</option>
                                                        <option value="percent">Percentage off base price</option>
                                                        <option value="qty">Quantity rules / breaks only</option>
                                                </Select>
                                        </Field>
                                        <div className="tss-form-grid">
                                                {draft.mode === 'override' ? (
                                                        <Field label={`Fixed unit price (${symbol || 'currency'})`} required hint="Non-negative, up to 4 decimals.">
                                                                <TextInput value={draft.priceOverride} onChange={(v) => set({ priceOverride: v })} placeholder="e.g. 8.5000" mono />
                                                        </Field>
                                                ) : null}
                                                {draft.mode === 'percent' ? (
                                                        <Field label="Percentage off" required hint="0–100, e.g. 8.25.">
                                                                <TextInput value={draft.percentOff} onChange={(v) => set({ percentOff: v })} placeholder="e.g. 8.25" mono />
                                                        </Field>
                                                ) : null}
                                                <Field label="Minimum qty">
                                                        <TextInput type="number" value={draft.minQty} onChange={(v) => set({ minQty: v })} min={1} max={100000} />
                                                </Field>
                                                <Field label="Quantity step">
                                                        <TextInput type="number" value={draft.qtyStep} onChange={(v) => set({ qtyStep: v })} min={1} max={10000} />
                                                </Field>
                                                <Field label="Priority" hint="Higher wins between overlapping scopes (-99…99).">
                                                        <TextInput type="number" value={draft.priority} onChange={(v) => set({ priority: v })} min={-99} max={99} />
                                                </Field>
                                        </div>

                                        <Field label="Quantity breaks" hint="Absolute prices from a threshold qty upward; thresholds ≥ 2, unique, ascending.">
                                                <div className="tss-breaks">
                                                        {draft.breaks.map((b, i) => (
                                                                <div className="tss-breaks__row" key={i}>
                                                                        <span className="tss-breaks__label">≥</span>
                                                                        <TextInput type="number" value={b.qty} onChange={(v) => setBreak(i, { qty: v })} min={2} mono ariaLabel={`Break ${i + 1} quantity`} />
                                                                        <span className="tss-breaks__label">@</span>
                                                                        <TextInput value={b.price} onChange={(v) => setBreak(i, { price: v })} placeholder="8.5000" mono ariaLabel={`Break ${i + 1} price`} />
                                                                        <Button size="sm" variant="danger" onClick={() => set({ breaks: draft.breaks.filter((_, j) => j !== i) })} ariaLabel={`Remove break ${i + 1}`}>
                                                                                Remove
                                                                        </Button>
                                                                </div>
                                                        ))}
                                                        <Button size="sm" onClick={() => set({ breaks: [...draft.breaks, { qty: '', price: '' }] })}>
                                                                + Add break
                                                        </Button>
                                                </div>
                                        </Field>

                                        <div className="tss-form-grid">
                                                <Field label="Valid from">
                                                        <TextInput type="date" value={draft.validFrom} onChange={(v) => set({ validFrom: v })} />
                                                </Field>
                                                <Field label="Valid to">
                                                        <TextInput type="date" value={draft.validTo} onChange={(v) => set({ validTo: v })} />
                                                </Field>
                                        </div>

                                        <div className="tss-wizard__actions">
                                                <Button variant="primary" busy={busy || busyLocal} onClick={submitRule}>
                                                        {draft.editingRuleId !== null ? 'Save rule' : 'Add rule'}
                                                </Button>
                                                <Button variant="ghost" onClick={() => setDraft(null)}>Cancel</Button>
                                        </div>
                                </div>
                        ) : null}

                        {rules.length === 0 ? (
                                <EmptyState
                                        title="No rules yet"
                                        hint="Add a rule: pick a scope (customer/product ids, 0 as wildcard), choose a fixed price or percentage off, and optionally quantity rules and breaks."
                                />
                        ) : (
                                <div className="tss-table-wrap">
                                        <table className="tss-table">
                                                <thead>
                                                        <tr>
                                                                <th>#</th>
                                                                <th>Scope</th>
                                                                <th>Price</th>
                                                                <th>Qty min</th>
                                                                <th>Step</th>
                                                                <th>Breaks</th>
                                                                <th>Priority</th>
                                                                <th>Valid</th>
                                                                <th aria-label="Actions" />
                                                        </tr>
                                                </thead>
                                                <tbody>
                                                        {rules.map((rule) => (
                                                                <tr key={rule.id}>
                                                                        <td className="tss-mono">{rule.id}</td>
                                                                        <td>{ruleScopeLabel(rule)}</td>
                                                                        <td>{rulePriceLabel(rule, symbol)}</td>
                                                                        <td>{rule.min_qty}</td>
                                                                        <td>{rule.qty_step}</td>
                                                                        <td className="tss-dim">{breaksLabel(rule.qty_breaks, symbol)}</td>
                                                                        <td className="tss-dim">{rule.priority}</td>
                                                                        <td className="tss-dim">
                                                                                {rule.valid_from || rule.valid_to
                                                                                        ? `${rule.valid_from ? formatDate(rule.valid_from) : '…'} → ${rule.valid_to ? formatDate(rule.valid_to) : '…'}`
                                                                                        : 'always'}
                                                                        </td>
                                                                        <td className="tss-cell-actions">
                                                                                <Button size="sm" onClick={() => setDraft(draftFromRule(rule))}>Edit</Button>
                                                                                <Button size="sm" variant="danger" busy={busyLocal} onClick={() => removeRule(rule.id)}>Delete</Button>
                                                                        </td>
                                                                </tr>
                                                        ))}
                                                </tbody>
                                        </table>
                                </div>
                        )}
                </Card>
        );
}

/* ------------------------------------------------------------ Customers */

function BookCustomersCard(props: { bookId: number; customers: readonly AssignedCustomer[]; busy: boolean; reload: () => void }): ReactElement {
        const { bookId, customers, busy, reload } = props;
        const [search, setSearch] = useState('');
        const [busyLocal, setBusyLocal] = useState(false);
        const debounced = useDebounced(search, 350);
        const results = useResource<CustomersResponse>(
                debounced === '' ? null : `customers?search=${encodeURIComponent(debounced)}&per_page=6`,
        );

        const assign = (customerId: number, name: string): void => {
                setBusyLocal(true);
                apiPost(`pricebooks/${bookId}/customers`, { customer_id: customerId })
                        .then(() => {
                                toast.success(`${name} assigned to this book (a customer belongs to exactly one book).`);
                                reload();
                        })
                        .catch((err: unknown) => toast.error(errorMessage(err)))
                        .finally(() => setBusyLocal(false));
        };

        const unassign = (customerId: number, name: string): void => {
                setBusyLocal(true);
                apiDelete(`pricebooks/${bookId}/customers/${customerId}`)
                        .then(() => {
                                toast.success(`${name} unassigned — line prices fall back to base prices.`);
                                reload();
                        })
                        .catch((err: unknown) => toast.error(errorMessage(err)))
                        .finally(() => setBusyLocal(false));
        };

        return (
                <Card
                        title={`Assigned customers (${customers.length})`}
                        subtitle="Assignment is exclusive: assigning a customer here moves them off any previous book"
                >
                        {customers.length === 0 ? (
                                <EmptyState title="No customers assigned" hint="Search for a customer below to assign them to this price book." />
                        ) : (
                                <ul className="tss-assigned">
                                        {customers.map((customer) => (
                                                <li key={customer.id} className="tss-assigned__item">
                                                        <span className="tss-picker__text">
                                                                <span className="tss-picker__name">{customer.display_name}</span>
                                                                <span className="tss-picker__sub">{customer.email || 'no email'}</span>
                                                                {customer.billing_company ? <span className="tss-picker__sub">{customer.billing_company}</span> : null}
                                                        </span>
                                                        <Button size="sm" variant="danger" busy={busy || busyLocal} onClick={() => unassign(customer.id, customer.display_name)}>
                                                                Unassign
                                                        </Button>
                                                </li>
                                        ))}
                                </ul>
                        )}

                        <Field label="Assign a customer" hint="Search by name, email or company.">
                                <SearchInput value={search} onChange={setSearch} placeholder="Search customers to assign…" />
                        </Field>
                        {results.loading && !results.data ? <Skeleton rows={2} /> : null}
                        {results.error ? <ErrorBanner message={results.error} onRetry={results.reload} /> : null}
                        {results.data && search !== '' ? (
                                results.data.items.length === 0 ? (
                                        <p className="tss-dim tss-note-inline">No customers match “{search}”.</p>
                                ) : (
                                        <ul className="tss-picker">
                                                {results.data.items.map((customer) => {
                                                        const already = customers.some((c) => c.id === customer.id);
                                                        return (
                                                                <li key={customer.id}>
                                                                        <div className="tss-picker__item tss-picker__item--static">
                                                                                <span className="tss-picker__text">
                                                                                        <span className="tss-picker__name">{customer.display_name}</span>
                                                                                        <span className="tss-picker__sub">{customer.email || 'no email'}</span>
                                                                                </span>
                                                                                <Button
                                                                                        size="sm"
                                                                                        variant={already ? 'secondary' : 'primary'}
                                                                                        disabled={already || busy || busyLocal}
                                                                                        busy={busyLocal}
                                                                                        onClick={() => assign(customer.id, customer.display_name)}
                                                                                >
                                                                                        {already ? 'Already assigned' : 'Assign'}
                                                                                </Button>
                                                                        </div>
                                                                </li>
                                                        );
                                                })}
                                        </ul>
                                )
                        ) : null}
                </Card>
        );
}

/* -------------------------------------------------------------- Preview */

function PreviewCard(props: { bookId: number }): ReactElement {
        const { bookId } = props;
        const symbol = currencySymbol();
        const [customerId, setCustomerId] = useState('');
        const [productId, setProductId] = useState('');
        const [variationId, setVariationId] = useState('');
        const [qty, setQty] = useState('1');
        const [busy, setBusy] = useState(false);
        const [resolution, setResolution] = useState<PriceResolution | null>(null);
        const [error, setError] = useState<string | null>(null);

        const run = (): void => {
                if (Number(customerId) < 1 || Number(productId) < 1) {
                        toast.error('Preview needs a customer id and a product id.');
                        return;
                }
                setBusy(true);
                setError(null);
                const params = new URLSearchParams({
                        customer_id: customerId,
                        product_id: productId,
                        variation_id: variationId === '' ? '0' : variationId,
                        qty: String(Math.max(1, Number(qty) || 1)),
                });
                apiGet<PriceResolution>(`pricebooks/${bookId}/preview?${params.toString()}`)
                        .then(setResolution)
                        .catch((err: unknown) => {
                                setError(errorMessage(err));
                                setResolution(null);
                        })
                        .finally(() => setBusy(false));
        };

        return (
                <Card title="Price preview" subtitle="Resolve a live price exactly as the pricing engine would for a packet line">
                        <div className="tss-form-grid">
                                <Field label="Customer id" required>
                                        <TextInput type="number" value={customerId} onChange={setCustomerId} min={0} mono placeholder="e.g. 12" />
                                </Field>
                                <Field label="Product id" required>
                                        <TextInput type="number" value={productId} onChange={setProductId} min={0} mono placeholder="e.g. 348" />
                                </Field>
                                <Field label="Variation id">
                                        <TextInput type="number" value={variationId} onChange={setVariationId} min={0} mono placeholder="0" />
                                </Field>
                                <Field label="Quantity">
                                        <TextInput type="number" value={qty} onChange={setQty} min={1} />
                                </Field>
                        </div>
                        <div className="tss-wizard__actions">
                                <Button variant="primary" busy={busy} onClick={run}>Resolve price</Button>
                        </div>
                        <ErrorBanner message={error} />
                        {resolution ? (
                                <div className="tss-resolution">
                                        <h4 className="tss-resolution__title">Resolution</h4>
                                        <MetaList
                                                rows={[
                                                        { label: 'Unit price', value: fmtMoney(resolution.unit_price, symbol) },
                                                        { label: 'Source', value: <Badge tone={resolution.source === 'base' ? 'neutral' : 'teal'}>{humanize(resolution.source)}</Badge> },
                                                        { label: 'Rule', value: resolution.rule_id ? `#${resolution.rule_id}` : '—' },
                                                        { label: 'Min qty', value: String(resolution.min_qty) },
                                                        { label: 'Qty step', value: String(resolution.qty_step) },
                                                        { label: 'Explanation', value: resolution.explanation },
                                                        { label: 'Qty breaks', value: breaksLabel(resolution.qty_breaks, symbol) },
                                                ]}
                                        />
                                        {resolution.warnings.length > 0 ? (
                                                <ul className="tss-resolution__warnings">
                                                        {resolution.warnings.map((w, i) => <li key={i}>{w}</li>)}
                                                </ul>
                                        ) : null}
                                </div>
                        ) : null}
                </Card>
        );
}
