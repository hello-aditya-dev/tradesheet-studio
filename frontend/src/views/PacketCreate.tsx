import type { ReactElement } from '../types/wp';
import { useCallback, useEffect, useMemo, useRef, useState } from '../wp';
import { apiDelete, apiGet, apiPost, currencySymbol, errorMessage } from '../api';
import { breaksLabel, humanize } from '../format';
import { estimateTotal, money as fmtMoney, mulQty } from '../money';
import { useDebounced, useResource } from '../hooks';
import { TEMPLATES, type View, type ViewProps } from '../nav';
import { doDownloadDocExplicit } from '../packetActions';
import { confirm } from '../components/confirm';
import { toast } from '../components/toast';
import {
        Badge, Button, Card, CopyField, EmptyState, ErrorBanner, Field, MetaList, Pagination, SearchInput, Select,
        Skeleton, StepIndicator, TextArea, TextInput,
} from '../components/ui';
import type {
        AddLineResponse, Customer, CustomersResponse, PacketDetail, PacketLine, PacketTerms,
        PriceBook, PriceResolution, Preflight, Product, ProductsResponse, PublishResult, SettingsResponse, Variation, VariationsResponse,
} from '../types/api';

const STEPS: readonly string[] = ['Buyer', 'Products', 'Pricing & terms', 'Presentation', 'Review & publish'];

interface TermsDraft {
        payment_terms: string;
        delivery_terms: string;
        lead_time: string;
        notes: string;
        min_order_value: string;
}

const EMPTY_TERMS: TermsDraft = {
        payment_terms: '',
        delivery_terms: '',
        lead_time: '',
        notes: '',
        min_order_value: '',
};

export function PacketCreateView({ go, resumeId }: ViewProps & { resumeId?: number }): ReactElement {
        const [step, setStep] = useState(1);
        const [packetId, setPacketId] = useState<number | null>(null);
        const [packetRef, setPacketRef] = useState<string | null>(null);
        const [buyer, setBuyer] = useState<Customer | null>(null);
        const [title, setTitle] = useState('');
        const [pricebookId, setPricebookId] = useState('');
        const [validUntil, setValidUntil] = useState('');
        const [terms, setTerms] = useState<TermsDraft>(EMPTY_TERMS);
        const [lines, setLines] = useState<PacketLine[]>([]);
        const [template, setTemplate] = useState('trade_table');
        const [publishResult, setPublishResult] = useState<PublishResult | null>(null);
        const [preflight, setPreflight] = useState<Preflight | null>(null);
        const [preflightBusy, setPreflightBusy] = useState(false);
        const [mutationBusy, setMutationBusy] = useState(false);

        const books = useResource<{ items: PriceBook[] }>('pricebooks');
        const settings = useResource<SettingsResponse>('settings');
        const symbol = currencySymbol();

        const seeded = useRef(false);
        const resume = useResource<PacketDetail>(typeof resumeId === 'number' ? `packets/${resumeId}` : null);

        // Seed the wizard when resuming an existing draft.
        useEffect(() => {
                if (seeded.current || !resume.data) {
                        return;
                }
                const detail = resume.data;
                if (detail.packet.status !== 'draft') {
                        seeded.current = true;
                        toast.error('Only draft packets can be resumed in the composer.');
                        go({ name: 'packet', id: detail.packet.id });
                        return;
                }
                seeded.current = true;
                setPacketId(detail.packet.id);
                setPacketRef(detail.packet.ref);
                setTitle(detail.packet.title);
                setBuyer({
                        id: detail.packet.customer_id,
                        display_name: detail.packet.customer_name || `Customer #${detail.packet.customer_id}`,
                        email: detail.packet.customer_email,
                        billing_company: '',
                        pricebook_id: null,
                });
                setPricebookId(detail.packet.pricebook_id ? String(detail.packet.pricebook_id) : '');
                setValidUntil(detail.packet.valid_until ? detail.packet.valid_until.slice(0, 10) : '');
                setLines(detail.lines);
                const t = detail.terms as PacketTerms;
                setTerms({
                        payment_terms: t.payment_terms ?? '',
                        delivery_terms: t.delivery_terms ?? '',
                        lead_time: t.lead_time ?? '',
                        notes: t.notes ?? '',
                        min_order_value: t.min_order_value ?? '',
                });
                if (detail.lines.length > 0) {
                        setStep(2);
                }
        }, [resume.data, go]);

        useEffect(() => {
                if (settings.data && !publishResult && !resumeId) {
                        setTemplate(settings.data.settings.default_template || 'trade_table');
                }
        }, [settings.data, publishResult, resumeId]);

        const refreshLines = useCallback(async (): Promise<void> => {
                if (packetId === null) {
                        return;
                }
                const detail = await apiGet<PacketDetail>(`packets/${packetId}`);
                setLines(detail.lines);
        }, [packetId]);

        const refreshPreflight = useCallback(async (): Promise<void> => {
                if (packetId === null) {
                        return;
                }
                setPreflightBusy(true);
                try {
                        const result = await apiGet<Preflight>(`packets/${packetId}/preflight`);
                        setPreflight(result);
                } catch (err) {
                        toast.error(errorMessage(err));
                } finally {
                        setPreflightBusy(false);
                }
        }, [packetId]);

        // Keep preflight fresh whenever lines change on a live draft.
        useEffect(() => {
                if (packetId !== null && lines.length >= 0 && step >= 2 && !publishResult) {
                        void refreshPreflight();
                }
                // eslint-disable-next-line react-hooks/exhaustive-deps
        }, [packetId, lines]);

        const bookName = (id: string): string => {
                if (id === '') {
                        return 'None';
                }
                const book = books.data?.items.find((b) => b.id === Number(id));
                return book ? book.name : `#${id}`;
        };

        const estimate = useMemo(
                () => estimateTotal(lines.map((l) => ({ unit_price: l.unit_price, qty: l.qty_min }))),
                [lines],
        );

        const run = (fn: () => Promise<void>): void => {
                if (mutationBusy) {
                        return;
                }
                setMutationBusy(true);
                fn()
                        .catch((err: unknown) => toast.error(errorMessage(err)))
                        .finally(() => setMutationBusy(false));
        };

        const discardDraft = (): void => {
                if (packetId === null) {
                        return;
                }
                confirm({
                        title: 'Discard this draft?',
                        message: 'The draft packet and all its lines are deleted permanently.',
                        confirmLabel: 'Delete draft',
                        danger: true,
                }).then((proceed) => {
                        if (!proceed) {
                                return;
                        }
                        apiDelete(`packets/${packetId}`)
                                .then(() => {
                                        toast.success('Draft discarded.');
                                        setPacketId(null);
                                        setPacketRef(null);
                                        setBuyer(null);
                                        setTitle('');
                                        setPricebookId('');
                                        setValidUntil('');
                                        setTerms(EMPTY_TERMS);
                                        setLines([]);
                                        setPreflight(null);
                                        setPublishResult(null);
                                        setStep(1);
                                })
                                .catch((err: unknown) => toast.error(errorMessage(err)));
                });
        };

        const rail = (
                <aside className="tss-wizard__rail">
                        <h3 className="tss-wizard__rail-title">Summary</h3>
                        <MetaList
                                rows={[
                                        { label: 'Buyer', value: buyer ? `${buyer.display_name}${buyer.billing_company ? ` · ${buyer.billing_company}` : ''}` : '—' },
                                        { label: 'Packet', value: packetRef ? <span className="tss-mono">{packetRef}</span> : 'not created yet' },
                                        { label: 'Products', value: String(lines.length) },
                                        { label: 'Price book', value: bookName(pricebookId) },
                                        {
                                                label: 'Validity',
                                                value: validUntil !== ''
                                                        ? validUntil
                                                        : settings.data
                                                                ? `Default (${settings.data.settings.default_validity_days} days)`
                                                                : 'Default',
                                        },
                                        { label: 'Estimated total', value: fmtMoney(estimate, symbol) },
                                        {
                                                label: 'Preflight',
                                                value: preflight
                                                        ? `${preflight.errors.length} error${preflight.errors.length === 1 ? '' : 's'} · ${preflight.warnings.length} warning${preflight.warnings.length === 1 ? '' : 's'}`
                                                        : '—',
                                        },
                                        { label: 'Template', value: TEMPLATES.find((t) => t.id === template)?.label ?? template },
                                ]}
                        />
                        {packetId !== null ? (
                                <Button variant="ghost" size="sm" onClick={discardDraft}>Discard draft</Button>
                        ) : null}
                        {packetRef ? (
                                <Button variant="ghost" size="sm" onClick={() => (packetId !== null ? go({ name: 'packet', id: packetId }) : undefined)}>
                                        Open packet detail
                                </Button>
                        ) : null}
                </aside>
        );

        return (
                <div className="tss-view">
                        <div className="tss-pagehead tss-wizard__head">
                                <div className="tss-pagehead__text">
                                        <h1 className="tss-pagehead__title">Create packet</h1>
                                        <p className="tss-pagehead__subtitle">
                                                Five steps from buyer to an immutable, checksummed offer version.
                                                {packetRef ? <span> Draft <span className="tss-mono">{packetRef}</span> in progress.</span> : null}
                                        </p>
                                </div>
                        </div>

                        <div className="tss-wizard">
                                <div className="tss-wizard__main">
                                        <StepIndicator steps={STEPS} current={step} />

                                        {publishResult ? (
                                                <PublishedPanel
                                                        result={publishResult}
                                                        packetId={packetId}
                                                        refLabel={packetRef ?? ''}
                                                        estimate={estimate}
                                                        go={go}
                                                />
                                        ) : (
                                                <>
                                                        {step === 1 ? (
                                                                <BuyerStep
                                                                        buyer={buyer}
                                                                        setBuyer={setBuyer}
                                                                        locked={packetId !== null}
                                                                        title={title}
                                                                        setTitle={setTitle}
                                                                        pricebookId={pricebookId}
                                                                        setPricebookId={setPricebookId}
                                                                        books={books.data?.items ?? []}
                                                                        busy={mutationBusy}
                                                                        onNext={() => run(async () => {
                                                                                if (!buyer || title.trim() === '') {
                                                                                        toast.error('Pick a buyer and give the packet a title first.');
                                                                                        return;
                                                                                }
                                                                                if (packetId === null) {
                                                                                        const created = await apiPost<{ id: number; ref: string }>('packets', {
                                                                                                customer_id: buyer.id,
                                                                                                title: title.trim(),
                                                                                                ...(pricebookId !== '' ? { pricebook_id: Number(pricebookId) } : {}),
                                                                                        });
                                                                                        setPacketId(created.id);
                                                                                        setPacketRef(created.ref);
                                                                                        toast.success(`Draft ${created.ref} created — add products next.`);
                                                                                } else {
                                                                                        await apiPost(`packets/${packetId}`, { title: title.trim() });
                                                                                }
                                                                                setStep(2);
                                                                        })}
                                                                />
                                                        ) : null}

                                                        {step === 2 ? (
                                                                <ProductsStep
                                                                        packetId={packetId}
                                                                        lines={lines}
                                                                        symbol={symbol}
                                                                        busy={mutationBusy}
                                                                        refreshLines={refreshLines}
                                                                        run={run}
                                                                />
                                                        ) : null}

                                                        {step === 3 ? (
                                                                <TermsStep
                                                                        books={books.data?.items ?? []}
                                                                        pricebookId={pricebookId}
                                                                        setPricebookId={setPricebookId}
                                                                        validUntil={validUntil}
                                                                        setValidUntil={setValidUntil}
                                                                        terms={terms}
                                                                        setTerms={setTerms}
                                                                        symbol={symbol}
                                                                        defaultValidityDays={settings.data?.settings.default_validity_days ?? null}
                                                                        busy={mutationBusy}
                                                                        onBack={() => setStep(2)}
                                                                        onNext={() => run(async () => {
                                                                                if (packetId === null) {
                                                                                        return;
                                                                                }
                                                                                const body: Record<string, unknown> = {
                                                                                        pricebook_id: pricebookId === '' ? 0 : Number(pricebookId),
                                                                                        terms: {
                                                                                                payment_terms: terms.payment_terms,
                                                                                                delivery_terms: terms.delivery_terms,
                                                                                                lead_time: terms.lead_time,
                                                                                                notes: terms.notes,
                                                                                                min_order_value: terms.min_order_value.trim(),
                                                                                        },
                                                                                };
                                                                                if (validUntil !== '') {
                                                                                        body.valid_until = validUntil;
                                                                                }
                                                                                await apiPost(`packets/${packetId}`, body);
                                                                                toast.success('Pricing and terms saved.');
                                                                                setStep(4);
                                                                        })}
                                                                />
                                                        ) : null}

                                                        {step === 4 ? (
                                                                <PresentationStep
                                                                        template={template}
                                                                        setTemplate={setTemplate}
                                                                        docSize={settings.data?.settings.doc_size ?? null}
                                                                        onBack={() => setStep(3)}
                                                                        onNext={() => {
                                                                                void refreshPreflight();
                                                                                setStep(5);
                                                                        }}
                                                                />
                                                        ) : null}

                                                        {step === 5 ? (
                                                                <ReviewStep
                                                                        packetId={packetId}
                                                                        packetRef={packetRef}
                                                                        template={template}
                                                                        preflight={preflight}
                                                                        preflightBusy={preflightBusy}
                                                                        refreshPreflight={refreshPreflight}
                                                                        busy={mutationBusy}
                                                                        lines={lines}
                                                                        symbol={symbol}
                                                                        estimate={estimate}
                                                                        onBack={() => setStep(4)}
                                                                        onPublished={(result) => {
                                                                                setPublishResult(result);
                                                                                toast.success(`Version ${result.version_no} published.`);
                                                                        }}
                                                                        onLinesChanged={refreshLines}
                                                                />
                                                        ) : null}
                                                </>
                                        )}

                                        {!publishResult ? (
                                                <div className="tss-wizard__nav">
                                                        {step > 1 ? <Button onClick={() => setStep(step - 1)}>Back</Button> : null}
                                                        <span className="tss-wizard__stepnote">
                                                                {packetId === null
                                                                        ? 'The draft packet is created when you continue past the buyer step.'
                                                                        : 'Draft changes are saved per action; publishing freezes the version.'}
                                                        </span>
                                                </div>
                                        ) : null}
                                </div>
                                {rail}
                        </div>
                </div>
        );
}

/* -------------------------------------------------------------- Step 1 */

function BuyerStep(props: {
        buyer: Customer | null;
        setBuyer: (customer: Customer | null) => void;
        locked: boolean;
        title: string;
        setTitle: (v: string) => void;
        pricebookId: string;
        setPricebookId: (v: string) => void;
        books: readonly PriceBook[];
        busy: boolean;
        onNext: () => void;
}): ReactElement {
        const { buyer, setBuyer, locked, title, setTitle, pricebookId, setPricebookId, books, busy, onNext } = props;
        const [search, setSearch] = useState('');
        const debounced = useDebounced(search, 300);
        const results = useResource<CustomersResponse>(
                debounced === '' ? 'customers?per_page=8' : `customers?search=${encodeURIComponent(debounced)}&per_page=8`,
        );

        const selectedBook = books.find((b) => b.id === buyer?.pricebook_id);

        return (
                <Card title="1 · Choose the buyer" subtitle="The buyer's assigned price book drives automatic line pricing">
                        <Field label="Packet title" required hint="Shown to the buyer on the portal and documents.">
                                <TextInput value={title} onChange={setTitle} placeholder="e.g. Spring reorder — Kaur Enterprises" />
                        </Field>

                        {locked && buyer ? (
                                <div className="tss-buyercard tss-buyercard--locked">
                                        <p className="tss-buyercard__name">{buyer.display_name}</p>
                                        <p className="tss-dim">{buyer.email || 'No email on file'}</p>
                                        {selectedBook ? (
                                                <p className="tss-dim">Assigned price book: <Badge tone="teal">{selectedBook.name}</Badge></p>
                                        ) : (
                                                <p className="tss-dim">No price book assigned — line prices fall back to WooCommerce base prices.</p>
                                        )}
                                        <p className="tss-dim tss-note-inline">The buyer is fixed once the draft is created.</p>
                                </div>
                        ) : (
                                <>
                                        <Field label="Find customer" hint="Search by name, email or company; results show the assigned price book.">
                                                <SearchInput value={search} onChange={setSearch} placeholder="Search customers…" autoFocus />
                                        </Field>
                                        {results.loading && !results.data ? <Skeleton rows={3} /> : null}
                                        <ErrorBanner message={results.error} onRetry={results.reload} />
                                        {results.data ? (
                                                results.data.items.length === 0 ? (
                                                        <EmptyState
                                                                title="No customers found"
                                                                hint={debounced === '' ? 'No WooCommerce customer accounts exist yet.' : 'Try a different search term.'}
                                                        />
                                                ) : (
                                                        <ul className="tss-picker">
                                                                {results.data.items.map((customer) => {
                                                                        const book = books.find((b) => b.id === customer.pricebook_id);
                                                                        const selected = buyer?.id === customer.id;
                                                                        return (
                                                                                <li key={customer.id}>
                                                                                        <button
                                                                                                type="button"
                                                                                                className={`tss-picker__item${selected ? ' is-selected' : ''}`}
                                                                                                onClick={() => {
                                                                                                        if (!locked) {
                                                                                                                                                                                                                                setBuyer(customer);
                                                                                                        }
                                                                                                }}
                                                                                                aria-pressed={selected}
                                                                                        >
                                                                                                <span className="tss-picker__radio" aria-hidden="true" />
                                                                                                <span className="tss-picker__text">
                                                                                                        <span className="tss-picker__name">{customer.display_name}</span>
                                                                                                        <span className="tss-picker__sub">{customer.email || 'no email'}</span>
                                                                                                        {customer.billing_company ? <span className="tss-picker__sub">{customer.billing_company}</span> : null}
                                                                                                </span>
                                                                                                <span className="tss-picker__book">
                                                                                                        {book ? <Badge tone="teal">{book.name}</Badge> : <span className="tss-dim">no book</span>}
                                                                                                </span>
                                                                                        </button>
                                                                                </li>
                                                                        );
                                                                })}
                                                        </ul>
                                                )
                                        ) : null}
                                </>
                        )}

                        <Field label="Reference price book (optional)" hint="Recorded on the packet as context. Line prices resolve from the book assigned to the buyer in the Customers view.">
                                <Select value={pricebookId} onChange={setPricebookId} disabled={locked}>
                                        <option value="">No price book</option>
                                        {books.map((book) => (
                                                <option key={book.id} value={String(book.id)}>
                                                        {book.name} {book.status !== 'active' ? '(inactive)' : ''}
                                                </option>
                                        ))}
                                </Select>
                        </Field>

                        <div className="tss-wizard__actions">
                                <Button
                                        variant="primary"
                                        busy={busy}
                                        disabled={buyer === null || title.trim() === ''}
                                        onClick={onNext}
                                        title={buyer === null ? 'Select a buyer first' : undefined}
                                >
                                        {locked ? 'Save title & continue' : 'Create draft & continue'}
                                </Button>
                        </div>
                </Card>
        );
}

/* -------------------------------------------------------------- Step 2 */

function ProductsStep(props: {
        packetId: number | null;
        lines: PacketLine[];
        symbol: string;
        busy: boolean;
        refreshLines: () => Promise<void>;
        run: (fn: () => Promise<void>) => void;
}): ReactElement {
        const { packetId, lines, symbol, busy, refreshLines, run } = props;

        const [search, setSearch] = useState('');
        const [page, setPage] = useState(1);
        const debounced = useDebounced(search, 300);
        const PER = 8;
        const products = useResource<ProductsResponse>(
                `products?per_page=${PER}&page=${page}` + (debounced !== '' ? `&search=${encodeURIComponent(debounced)}` : ''),
        );

        const [expanded, setExpanded] = useState<number | null>(null);
        const [variations, setVariations] = useState<Record<number, Variation[] | null>>({});
        const [lastResolution, setLastResolution] = useState<{ name: string; resolution: PriceResolution } | null>(null);

        const loadVariations = (productId: number): void => {
                if (variations[productId] !== undefined) {
                        return;
                }
                setVariations((prev) => ({ ...prev, [productId]: null }));
                apiGet<VariationsResponse>(`products/${productId}/variations`)
                        .then((res) => setVariations((prev) => ({ ...prev, [productId]: res.items })))
                        .catch((err: unknown) => {
                                toast.error(errorMessage(err));
                                setVariations((prev) => ({ ...prev, [productId]: [] }));
                        });
        };

        const addLine = (product: { id: number; name: string }, variation?: Variation): void => {
                if (packetId === null) {
                        return;
                }
                run(async () => {
                        const body: Record<string, unknown> = {
                                product_id: product.id,
                                qty_min: 1,
                                qty_step: 1,
                        };
                        if (variation) {
                                body.variation_id = variation.variation_id;
                        }
                        const res = await apiPost<AddLineResponse>(`packets/${packetId}/lines`, body);
                        if (res.resolution) {
                                setLastResolution({ name: variation ? `${product.name} — ${variation.summary}` : product.name, resolution: res.resolution });
                        }
                        await refreshLines();
                        toast.success('Line added.');
                });
        };

        return (
                <Card title="2 · Choose products" subtitle="Search the catalog; variable products expand into their variations. Prices resolve through the buyer's assigned price book.">
                        <Field label="Search catalog">
                                <SearchInput
                                        value={search}
                                        onChange={(v) => { setSearch(v); setPage(1); }}
                                        placeholder="Search products…"
                                />
                        </Field>

                        {products.loading && !products.data ? <Skeleton rows={4} /> : null}
                        <ErrorBanner message={products.error} onRetry={products.reload} />

                        {products.data ? (
                                products.data.items.length === 0 ? (
                                        <EmptyState
                                                title="No products found"
                                                hint={debounced === '' ? 'No published simple or variable products exist in the store yet.' : 'Try a different search term.'}
                                        />
                                ) : (
                                        <div className="tss-table-wrap">
                                                <table className="tss-table">
                                                        <thead>
                                                                <tr>
                                                                        <th>Product</th>
                                                                        <th>SKU</th>
                                                                        <th>Type</th>
                                                                        <th>Base price</th>
                                                                        <th>Stock</th>
                                                                        <th aria-label="Add" />
                                                                </tr>
                                                        </thead>
                                                        <tbody>
                                                                {products.data.items.map((product: Product) => (
                                                                        <ProductRow
                                                                                key={product.id}
                                                                                product={product}
                                                                                symbol={symbol}
                                                                                busy={busy}
                                                                                onAdd={() => addLine(product)}
                                                                                expanded={expanded === product.id}
                                                                                onToggleExpand={() => {
                                                                                        const next = expanded === product.id ? null : product.id;
                                                                                        setExpanded(next);
                                                                                        if (next !== null) {
                                                                                                loadVariations(next);
                                                                                        }
                                                                                }}
                                                                                variations={variations[product.id]}
                                                                                onAddVariation={(variation) => addLine(product, variation)}
                                                                        />
                                                                ))}
                                                        </tbody>
                                                </table>
                                        </div>
                                )
                        ) : null}

                        {products.data ? (
                                <Pagination
                                        page={page}
                                        hasPrev={page > 1}
                                        hasNext={(products.data.items.length === PER) && (products.data.total > page * PER)}
                                        onPage={setPage}
                                        total={products.data.total}
                                        per_page={PER}
                                        label="products"
                                />
                        ) : null}

                        {lastResolution ? (
                                <div className="tss-resolution">
                                        <h4 className="tss-resolution__title">Last price resolution — {lastResolution.name}</h4>
                                        <MetaList
                                                rows={[
                                                        { label: 'Unit price', value: fmtMoney(lastResolution.resolution.unit_price, symbol) },
                                                        { label: 'Source', value: <Badge tone={lastResolution.resolution.source === 'base' ? 'neutral' : 'teal'}>{humanize(lastResolution.resolution.source)}</Badge> },
                                                        { label: 'Explanation', value: lastResolution.resolution.explanation },
                                                        { label: 'Qty breaks', value: breaksLabel(lastResolution.resolution.qty_breaks, symbol) },
                                                ]}
                                        />
                                        {lastResolution.resolution.warnings.length > 0 ? (
                                                <ul className="tss-resolution__warnings">
                                                        {lastResolution.resolution.warnings.map((w, i) => <li key={i}>{w}</li>)}
                                                </ul>
                                        ) : null}
                                </div>
                        ) : null}

                        <h3 className="tss-subhead">Packet lines ({lines.length})</h3>
                        {lines.length === 0 ? (
                                <EmptyState
                                        title="No lines yet"
                                        hint="Add products above; each line's price resolves live through the buyer's price book."
                                />
                        ) : (
                                <LinesEditor
                                        packetId={packetId}
                                        lines={lines}
                                        symbol={symbol}
                                        busy={busy}
                                        refreshLines={refreshLines}
                                        run={run}
                                />
                        )}
                </Card>
        );
}

function ProductRow(props: {
        product: Product;
        symbol: string;
        busy: boolean;
        onAdd: () => void;
        expanded: boolean;
        onToggleExpand: () => void;
        variations: Variation[] | null | undefined;
        onAddVariation: (variation: Variation) => void;
}): ReactElement | null {
        const { product, symbol, busy, onAdd, expanded, onToggleExpand, variations, onAddVariation } = props;
        const isVariable = product.type === 'variable';

        return (
                <>
                        <tr>
                                <td className="tss-cell-title">{product.name}</td>
                                <td className="tss-mono">{product.sku || <span className="tss-dim">—</span>}</td>
                                <td><Badge tone={isVariable ? 'amber' : 'neutral'}>{product.type}</Badge></td>
                                <td>{fmtMoney(product.price, symbol)}</td>
                                <td className="tss-dim">{humanize(product.stock_status)}</td>
                                <td className="tss-cell-actions">
                                        {isVariable ? (
                                                <Button size="sm" onClick={onToggleExpand} ariaLabel={expanded ? 'Collapse variations' : 'Expand variations'}>
                                                        {expanded ? 'Hide variations' : 'Variations'}
                                                </Button>
                                        ) : (
                                                <Button size="sm" variant="primary" busy={busy} onClick={onAdd}>Add line</Button>
                                        )}
                                </td>
                        </tr>
                        {isVariable && expanded ? (
                                <tr className="tss-table__subrow">
                                        <td colSpan={6}>
                                                {!variations ? (
                                                        <Skeleton rows={2} />
                                                ) : variations.length === 0 ? (
                                                        <p className="tss-dim tss-note-inline">This variable product has no published variations.</p>
                                                ) : (
                                                        <ul className="tss-variations">
                                                                {variations.map((variation) => (
                                                                        <li key={variation.variation_id} className="tss-variations__item">
                                                                                <span className="tss-variations__summary">
                                                                                        {variation.summary || `Variation #${variation.variation_id}`}
                                                                                        {variation.sku ? <span className="tss-mono tss-dim"> {variation.sku}</span> : null}
                                                                                </span>
                                                                                <span className="tss-variations__meta">
                                                                                        {fmtMoney(variation.price, symbol)} · {humanize(variation.stock_status)}
                                                                                </span>
                                                                                <Button size="sm" variant="primary" busy={busy} onClick={() => onAddVariation(variation)}>
                                                                                        Add line
                                                                                </Button>
                                                                        </li>
                                                                ))}
                                                        </ul>
                                                )}
                                        </td>
                                </tr>
                        ) : null}
                </>
        );
}

function LinesEditor(props: {
        packetId: number | null;
        lines: PacketLine[];
        symbol: string;
        busy: boolean;
        refreshLines: () => Promise<void>;
        run: (fn: () => Promise<void>) => void;
}): ReactElement {
        const { packetId, lines, symbol, busy, refreshLines, run } = props;
        const [edits, setEdits] = useState<Record<number, { qtyMin: string; qtyStep: string; override: string }>>({});

        const editFor = (line: PacketLine): { qtyMin: string; qtyStep: string; override: string } =>
                edits[line.id] ?? {
                        qtyMin: String(line.qty_min),
                        qtyStep: String(line.qty_step),
                        override: line.price_source === 'packet_override' ? line.unit_price : '',
                };

        const setEdit = (lineId: number, patch: Partial<{ qtyMin: string; qtyStep: string; override: string }>): void => {
                const current = edits[lineId] ?? {
                        qtyMin: String(lines.find((l) => l.id === lineId)?.qty_min ?? 1),
                        qtyStep: String(lines.find((l) => l.id === lineId)?.qty_step ?? 1),
                        override: lines.find((l) => l.id === lineId)?.price_source === 'packet_override'
                                ? String(lines.find((l) => l.id === lineId)?.unit_price ?? '')
                                : '',
                };
                setEdits((prev) => ({ ...prev, [lineId]: { ...current, ...patch } }));
        };

        const isDirty = (line: PacketLine): boolean => {
                const edit = editFor(line);
                return Number(edit.qtyMin) !== line.qty_min
                        || Number(edit.qtyStep) !== line.qty_step
                        || (edit.override !== '' && edit.override !== line.unit_price);
        };

        const saveLine = (line: PacketLine): void => {
                if (packetId === null) {
                        return;
                }
                const edit = editFor(line);
                run(async () => {
                        const body: Record<string, unknown> = {
                                qty_min: Math.max(1, Number(edit.qtyMin) || 1),
                                qty_step: Math.max(1, Number(edit.qtyStep) || 1),
                        };
                        if (edit.override !== '' && edit.override !== line.unit_price) {
                                body.override_price = edit.override;
                        }
                        await apiPost(`packets/${packetId}/lines/${line.id}`, body);
                        setEdits((prev) => {
                                const next = { ...prev };
                                delete next[line.id];
                                return next;
                        });
                        await refreshLines();
                        toast.success(`Line ${line.row_id} updated.`);
                });
        };

        const removeLine = (line: PacketLine): void => {
                if (packetId === null) {
                        return;
                }
                run(async () => {
                        await apiDelete(`packets/${packetId}/lines/${line.id}`);
                        await refreshLines();
                        toast.success(`Line ${line.row_id} removed.`);
                });
        };

        const move = (index: number, dir: -1 | 1): void => {
                if (packetId === null) {
                        return;
                }
                const target = index + dir;
                if (target < 0 || target >= lines.length) {
                        return;
                }
                const next = [...lines];
                const tmp = next[index];
                next[index] = next[target];
                next[target] = tmp;
                run(async () => {
                        await apiPost(`packets/${packetId}/lines-order`, { order: next.map((l) => l.id) });
                        await refreshLines();
                });
        };

        return (
                <div className="tss-table-wrap">
                        <table className="tss-table tss-table--lines">
                                <thead>
                                        <tr>
                                                <th aria-label="Reorder" />
                                                <th>Row</th>
                                                <th>Product</th>
                                                <th>Qty min</th>
                                                <th>Step</th>
                                                <th>Override price</th>
                                                <th>Unit price</th>
                                                <th>Source</th>
                                                <th>Breaks</th>
                                                <th>Estimate</th>
                                                <th aria-label="Actions" />
                                        </tr>
                                </thead>
                                <tbody>
                                        {lines.map((line, index) => {
                                                const edit = editFor(line);
                                                const dirty = isDirty(line);
                                                return (
                                                        <tr key={line.id} className={dirty ? 'is-dirty' : undefined}>
                                                                <td className="tss-cell-order">
                                                                        <span className="tss-orderbtns">
                                                                                <button type="button" className="tss-iconbtn" disabled={index === 0 || busy} onClick={() => move(index, -1)} aria-label="Move line up">↑</button>
                                                                                <button type="button" className="tss-iconbtn" disabled={index === lines.length - 1 || busy} onClick={() => move(index, 1)} aria-label="Move line down">↓</button>
                                                                        </span>
                                                                </td>
                                                                <td className="tss-mono">{line.row_id}</td>
                                                                <td>
                                                                        <span className="tss-cell-strong">{line.name}</span>
                                                                        {line.variation_summary ? <span className="tss-cell-sub">{line.variation_summary}</span> : null}
                                                                </td>
                                                                <td className="tss-cell-narrow">
                                                                        <TextInput
                                                                                type="number"
                                                                                value={edit.qtyMin}
                                                                                onChange={(v) => setEdit(line.id, { qtyMin: v })}
                                                                                min={1}
                                                                                ariaLabel={`Minimum quantity for ${line.row_id}`}
                                                                        />
                                                                </td>
                                                                <td className="tss-cell-narrow">
                                                                        <TextInput
                                                                                type="number"
                                                                                value={edit.qtyStep}
                                                                                onChange={(v) => setEdit(line.id, { qtyStep: v })}
                                                                                min={1}
                                                                                ariaLabel={`Quantity step for ${line.row_id}`}
                                                                        />
                                                                </td>
                                                                <td className="tss-cell-narrow">
                                                                        <TextInput
                                                                                value={edit.override}
                                                                                onChange={(v) => setEdit(line.id, { override: v })}
                                                                                placeholder="—"
                                                                                mono
                                                                                ariaLabel={`Price override for ${line.row_id}`}
                                                                        />
                                                                </td>
                                                                <td className="tss-strong">{fmtMoney(line.unit_price, symbol)}</td>
                                                                <td>
                                                                        <Badge tone={line.price_source === 'packet_override' ? 'amber' : line.price_source === 'base' ? 'neutral' : 'teal'}>
                                                                                {humanize(line.price_source)}
                                                                        </Badge>
                                                                </td>
                                                                <td className="tss-dim">{breaksLabel(line.qty_breaks, symbol)}</td>
                                                                <td className="tss-dim">{fmtMoney(mulQty(line.unit_price, line.qty_min), symbol)}</td>
                                                                <td className="tss-cell-actions">
                                                                        <Button size="sm" variant="primary" disabled={!dirty} busy={busy} onClick={() => saveLine(line)}>Save</Button>
                                                                        <Button size="sm" variant="danger" busy={busy} onClick={() => removeLine(line)}>Remove</Button>
                                                                </td>
                                                        </tr>
                                                );
                                        })}
                                </tbody>
                        </table>
                </div>
        );
}


/* -------------------------------------------------------------- Step 3 */

function TermsStep(props: {
        books: readonly PriceBook[];
        pricebookId: string;
        setPricebookId: (v: string) => void;
        validUntil: string;
        setValidUntil: (v: string) => void;
        terms: TermsDraft;
        setTerms: (terms: TermsDraft) => void;
        symbol: string;
        defaultValidityDays: number | null;
        busy: boolean;
        onBack: () => void;
        onNext: () => void;
}): ReactElement {
        const { books, pricebookId, setPricebookId, validUntil, setValidUntil, terms, setTerms, symbol, defaultValidityDays, busy, onBack, onNext } = props;

        const set = (key: keyof TermsDraft, value: string): void => {
                setTerms({ ...terms, [key]: value });
        };

        return (
                <Card title="3 · Pricing & terms" subtitle="Validity window and commercial terms recorded with the packet">
                        <Field
                                label="Price book on packet"
                                hint="Context reference. Live line prices resolve from the book assigned to the buyer (Customers view) — re-open products to refresh resolutions."
                        >
                                <Select value={pricebookId} onChange={setPricebookId}>
                                        <option value="">No price book</option>
                                        {books.map((book) => (
                                                <option key={book.id} value={String(book.id)}>
                                                        {book.name} · priority {book.priority} {book.status !== 'active' ? '(inactive)' : ''}
                                                </option>
                                        ))}
                                </Select>
                        </Field>

                        <Field
                                label="Valid until"
                                hint={defaultValidityDays !== null ? `Leave empty to use the store default (${defaultValidityDays} days) at publish time.` : 'Leave empty to use the store default at publish time.'}
                        >
                                <TextInput type="date" value={validUntil} onChange={setValidUntil} />
                        </Field>

                        <div className="tss-form-grid">
                                <Field label="Payment terms">
                                        <TextInput value={terms.payment_terms} onChange={(v) => set('payment_terms', v)} placeholder="e.g. 50% advance, 50% on delivery" />
                                </Field>
                                <Field label="Delivery terms">
                                        <TextInput value={terms.delivery_terms} onChange={(v) => set('delivery_terms', v)} placeholder="e.g. Ex-works Pune, freight extra" />
                                </Field>
                                <Field label="Lead time">
                                        <TextInput value={terms.lead_time} onChange={(v) => set('lead_time', v)} placeholder="e.g. 10 working days" />
                                </Field>
                                <Field label={`Minimum order value (${symbol || 'currency'})`}>
                                        <TextInput value={terms.min_order_value} onChange={(v) => set('min_order_value', v)} placeholder="e.g. 25000" mono />
                                </Field>
                        </div>

                        <Field label="Notes for the buyer">
                                <TextArea value={terms.notes} onChange={(v) => set('notes', v)} rows={4} placeholder="Terms, exclusions or instructions shown on the offer documents." />
                        </Field>

                        <div className="tss-wizard__actions">
                                <Button onClick={onBack}>Back</Button>
                                <Button variant="primary" busy={busy} onClick={onNext}>Save & continue</Button>
                        </div>
                </Card>
        );
}

/* -------------------------------------------------------------- Step 4 */

function PresentationStep(props: {
        template: string;
        setTemplate: (v: string) => void;
        docSize: string | null;
        onBack: () => void;
        onNext: () => void;
}): ReactElement {
        const { template, setTemplate, docSize, onBack, onNext } = props;
        return (
                <Card title="4 · Presentation" subtitle="PDF template for the buyer-facing documents">
                        <div className="tss-templates" role="radiogroup" aria-label="Document template">
                                {TEMPLATES.map((t) => (
                                        <button
                                                key={t.id}
                                                type="button"
                                                className={`tss-templates__item${template === t.id ? ' is-selected' : ''}`}
                                                role="radio"
                                                aria-checked={template === t.id}
                                                onClick={() => setTemplate(t.id)}
                                        >
                                                <span className="tss-templates__label">{t.label}</span>
                                                <span className="tss-templates__desc">{t.description}</span>
                                        </button>
                                ))}
                        </div>
                        <p className="tss-note">
                                Document size: <strong>{docSize ? docSize.toUpperCase() : 'A4'}</strong> — configured in Settings.
                                Every publish also generates a CSV order sheet and a ZIP bundle alongside the PDF.
                        </p>
                        <div className="tss-wizard__actions">
                                <Button onClick={onBack}>Back</Button>
                                <Button variant="primary" onClick={onNext}>Continue to review</Button>
                        </div>
                </Card>
        );
}

/* -------------------------------------------------------------- Step 5 */

function ReviewStep(props: {
        packetId: number | null;
        packetRef: string | null;
        template: string;
        preflight: Preflight | null;
        preflightBusy: boolean;
        refreshPreflight: () => Promise<void>;
        busy: boolean;
        lines: PacketLine[];
        symbol: string;
        estimate: string;
        onBack: () => void;
        onPublished: (result: PublishResult) => void;
        onLinesChanged: () => Promise<void>;
}): ReactElement {
        const { packetId, packetRef, template, preflight, preflightBusy, refreshPreflight, busy, lines, symbol, estimate, onBack, onPublished, onLinesChanged } = props;
        const [publishing, setPublishing] = useState(false);

        const errors = preflight?.errors ?? [];
        const warnings = preflight?.warnings ?? [];
        const blocked = errors.length > 0 || lines.length === 0;

        const publish = (): void => {
                if (packetId === null || blocked) {
                        return;
                }
                confirm({
                        title: 'Publish this packet?',
                        message:
                                'Publishing freezes the current lines, terms and presentation data into an immutable version with a content checksum, generates the PDF/CSV/ZIP documents and issues a buyer share link. This cannot be undone — corrections require a new version via Revise.',
                        confirmLabel: 'Publish version',
                        danger: true,
                }).then((proceed) => {
                        if (!proceed || packetId === null) {
                                return;
                        }
                        setPublishing(true);
                        apiPost<PublishResult>(`packets/${packetId}/publish`, { template })
                                .then((result) => {
                                        onPublished(result);
                                })
                                .catch((err: unknown) => toast.error(errorMessage(err)))
                                .finally(() => setPublishing(false));
                });
        };

        return (
                <Card
                        title="5 · Review & publish"
                        subtitle={packetRef ? <span>Draft <span className="tss-mono">{packetRef}</span> · {lines.length} line{lines.length === 1 ? '' : 's'} · estimated {fmtMoney(estimate, symbol)}</span> : 'Draft'}
                        actions={<Button size="sm" variant="ghost" busy={preflightBusy} onClick={() => void refreshPreflight()}>Re-run preflight</Button>}
                >
                        {preflight === null ? (
                                <Skeleton rows={3} />
                        ) : (
                                <div className="tss-preflight">
                                        {errors.length === 0 && warnings.length === 0 ? (
                                                <div className="tss-preflight__ok">No findings — the packet is clean and ready to publish.</div>
                                        ) : null}
                                        {errors.length > 0 ? (
                                                <div className="tss-preflight__group tss-preflight__group--errors">
                                                        <h4>Blocking errors ({errors.length})</h4>
                                                        <ul>
                                                                {errors.map((f, i) => (
                                                                        <li key={`${f.code}-${i}`}><strong>{f.code}</strong> — {f.message}{f.line_id ? <span className="tss-dim"> (line #{f.line_id})</span> : null}</li>
                                                                ))}
                                                        </ul>
                                                </div>
                                        ) : null}
                                        {warnings.length > 0 ? (
                                                <div className="tss-preflight__group tss-preflight__group--warnings">
                                                        <h4>Warnings ({warnings.length})</h4>
                                                        <ul>
                                                                {warnings.map((f, i) => (
                                                                        <li key={`${f.code}-${i}`}><strong>{f.code}</strong> — {f.message}{f.line_id ? <span className="tss-dim"> (line #{f.line_id})</span> : null}</li>
                                                                ))}
                                                        </ul>
                                                        <p className="tss-dim tss-note-inline">Warnings do not block publication; they are recorded in the audit event.</p>
                                                </div>
                                        ) : null}
                                        {lines.length === 0 ? (
                                                <div className="tss-preflight__group tss-preflight__group--errors">
                                                        <h4>No lines</h4>
                                                        <p>Go back to step 2 and add at least one product line.</p>
                                                </div>
                                        ) : null}
                                </div>
                        )}

                        <div className="tss-wizard__actions">
                                <Button onClick={onBack}>Back</Button>
                                <Button
                                        variant="primary"
                                        busy={publishing || busy}
                                        disabled={blocked || publishing}
                                        onClick={publish}
                                        title={blocked ? 'Resolve blocking errors first' : undefined}
                                >
                                        Publish version
                                </Button>
                                <Button variant="ghost" onClick={() => void onLinesChanged()}>Refresh lines</Button>
                        </div>
                </Card>
        );
}

/* --------------------------------------------------------- Published */

function PublishedPanel(props: {
        result: PublishResult;
        packetId: number | null;
        refLabel: string;
        estimate: string;
        go: (view: View) => void;
}): ReactElement {
        const { result, packetId, refLabel, estimate, go } = props;
        const symbol = currencySymbol();
        const [busyDoc, setBusyDoc] = useState<string | null>(null);

        const download = (type: string, template: string): void => {
                if (packetId === null) {
                        return;
                }
                setBusyDoc(type);
                doDownloadDocExplicit(packetId, refLabel, type, template)
                        .catch((err: unknown) => toast.error(errorMessage(err)))
                        .finally(() => setBusyDoc(null));
        };

        return (
                <Card title="Version published" subtitle={`Version ${result.version_no} is immutable and live for the buyer`}>
                        <div className="tss-published">
                                <div className="tss-published__check">
                                        <span className="tss-published__mark" aria-hidden="true">✓</span>
                                        <div>
                                                <p className="tss-published__ref"><span className="tss-mono">{refLabel}</span> · version <strong>{result.version_no}</strong></p>
                                                {result.warnings.length > 0 ? (
                                                                                        <p className="tss-dim">{result.warnings.length} preflight warning{result.warnings.length === 1 ? '' : 's'} recorded in the audit event.</p>
                                                ) : (
                                                        <p className="tss-dim">Published clean — no preflight warnings.</p>
                                                )}
                                        </div>
                                </div>

                                <CopyField label="Content checksum" value={result.checksum} />
                                <CopyField label="Buyer share link" value={result.share_url} />
                                <p className="tss-dim tss-note-inline">The share link token is valid per the buyer token days setting; the packet detail view can issue fresh links.</p>

                                {result.document_error ? (
                                        <ErrorBanner message={`Document generation failed: ${result.document_error}`} />
                                ) : null}

                                <h4 className="tss-subhead">Documents ({result.documents.length})</h4>
                                {result.documents.length === 0 ? (
                                        <EmptyState title="No documents generated" hint="Regenerate documents from the packet detail view." />
                                ) : (
                                        <ul className="tss-doclist">
                                                {result.documents.map((doc) => (
                                                        <li key={doc.id} className="tss-doclist__item">
                                                                <span className="tss-doclist__type">{doc.type.toUpperCase()}</span>
                                                                <span className="tss-dim">{doc.template ? humanize(doc.template) : '—'}</span>
                                                                <Button
                                                                        size="sm"
                                                                        busy={busyDoc === doc.type}
                                                                        onClick={() => download(doc.type, doc.template)}
                                                                >
                                                                        Download
                                                                </Button>
                                                        </li>
                                                ))}
                                        </ul>
                                )}

                                {result.template ? <p className="tss-dim tss-note-inline">PDF template: {humanize(result.template)}.</p> : null}

                                <div className="tss-wizard__actions">
                                        {packetId !== null ? (
                                                <Button variant="primary" onClick={() => go({ name: 'packet', id: packetId })}>Open packet detail</Button>
                                        ) : null}
                                        <Button onClick={() => go({ name: 'packets' })}>Back to packets</Button>
                                        <Button variant="ghost" onClick={() => go({ name: 'overview' })}>Overview</Button>
                                </div>
                                <p className="tss-dim tss-note-inline">Estimated total at minimum quantities: {fmtMoney(estimate, symbol)}.</p>
                        </div>
                </Card>
        );
}
