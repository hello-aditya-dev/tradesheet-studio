import type { ReactElement } from '../types/wp';
import { type ViewProps } from '../nav';
import { Badge, Button, Card, PageHeader } from '../components/ui';

const PACKET_LEGEND: readonly { status: string; tone: 'neutral' | 'amber' | 'green' | 'red' | 'gray'; text: string }[] = [
        { status: 'draft', tone: 'neutral', text: 'Being composed; lines and terms editable.' },
        { status: 'issued', tone: 'amber', text: 'Immutable version live for the buyer.' },
        { status: 'submitted', tone: 'amber', text: 'Buyer sent a quote request; awaiting merchant action.' },
        { status: 'in_review', tone: 'amber', text: 'Merchant opened the request or issued a revision.' },
        { status: 'approved', tone: 'green', text: 'Merchant approved; buyer can accept.' },
        { status: 'accepted', tone: 'green', text: 'Buyer accepted the terms; convertible to an order.' },
        { status: 'ordered', tone: 'green', text: 'Converted into a WooCommerce order.' },
        { status: 'expired', tone: 'red', text: 'Validity window passed (or expired manually).' },
        { status: 'revoked', tone: 'red', text: 'Withdrawn; buyer tokens invalidated.' },
        { status: 'cancelled', tone: 'red', text: 'Cancelled before acceptance.' },
        { status: 'archived', tone: 'gray', text: 'Out of the pipeline; kept for audit.' },
];

const QUOTE_LEGEND: readonly { status: string; tone: 'neutral' | 'amber' | 'green' | 'red' | 'gray'; text: string }[] = [
        { status: 'requested', tone: 'amber', text: 'Fresh buyer request on an issued version.' },
        { status: 'in_review', tone: 'amber', text: 'Merchant is examining the request.' },
        { status: 'revised', tone: 'neutral', text: 'Merchant revision awaiting buyer acceptance.' },
        { status: 'approved', tone: 'green', text: 'Merchant approved; awaiting buyer acceptance.' },
        { status: 'accepted', tone: 'green', text: 'Buyer accepted — ready to convert.' },
        { status: 'ordered', tone: 'green', text: 'Converted to a WooCommerce order (idempotent).' },
        { status: 'rejected', tone: 'red', text: 'Rejected by the merchant.' },
        { status: 'superseded', tone: 'gray', text: 'Replaced by a newer revision; can never be accepted.' },
        { status: 'expired', tone: 'red', text: 'Quote validity window passed.' },
        { status: 'cancelled', tone: 'gray', text: 'Withdrawn.' },
];

const WORKFLOW: readonly { title: string; body: string }[] = [
        {
                title: '1 · Price book',
                body: 'Create a price book with scoped rules — a fixed price or a percentage off for a customer, product or variation (0 acts as a wildcard), plus quantity minimums, steps and break tiers. Assign customers to the book; a customer resolves prices from exactly one book.',
        },
        {
                title: '2 · Compose',
                body: 'Create a packet for a buyer and add products. Every line price is resolved server-side through the buyer’s book (or an explicit override), with the winning rule explained. Set the validity window and commercial terms, and pick the document template.',
        },
        {
                title: '3 · Publish (immutable)',
                body: 'Preflight checks for blocking problems (missing products, currency mismatches, empty packets). Publishing freezes lines, terms and presentation data into a version with a SHA-256 content checksum, marks prior versions superseded, generates PDF/CSV/ZIP documents and issues a token-gated buyer link. Issued versions are never edited in place.',
        },
        {
                title: '4 · Buyer portal',
                body: 'The buyer opens the share link, reviews the offer and submits quantities for a quote. Quantities are the only buyer input — prices always come from the frozen snapshot.',
        },
        {
                title: '5 · Integrity engine',
                body: 'On submission the quote is compared against the immutable version: price drift, removed or unavailable products, expired offers, currency and tax-context changes. Blocking findings stop the quote from proceeding; warnings are recorded.',
        },
        {
                title: '6 · Review · revise',
                body: 'The merchant reviews the request, approves or rejects it, or issues a revision — a NEW immutable quote row with edited prices/quantities that the buyer must explicitly accept. The old revision becomes superseded and can never be accepted.',
        },
        {
                title: '7 · Accept · order',
                body: 'Once the buyer accepts, the merchant converts the quote into a WooCommerce order at the quoted prices (never the current public prices). Conversion is idempotent — one order per quote, with a customer payment link.',
        },
];

export function HelpView({ go }: ViewProps): ReactElement {
        return (
                <div className="tss-view">
                        <PageHeader
                                title="Help & workflow"
                                subtitle="How DealPackets fit together, what each state means, and where the technical documentation lives"
                        />

                        <Card title="The DealPacket workflow" subtitle="From pricing rules to a WooCommerce order">
                                <div className="tss-help-grid">
                                        {WORKFLOW.map((step) => (
                                                <div key={step.title} className="tss-help-step">
                                                        <h3>{step.title}</h3>
                                                        <p>{step.body}</p>
                                                </div>
                                        ))}
                                </div>
                        </Card>

                        <Card title="Composer guide" subtitle="The five steps of the Create Packet wizard">
                                <ol className="tss-helplist">
                                        <li>
                                                <strong>Buyer</strong> — search WooCommerce customers, give the packet a title (required) and optionally
                                                reference a price book. The draft packet is created when you continue.
                                        </li>
                                        <li>
                                                <strong>Products</strong> — search the catalog; variable products expand into variations. Each added line
                                                shows its resolved price and source. Adjust qty min/step, set an override price, reorder or remove lines.
                                        </li>
                                        <li>
                                                <strong>Pricing &amp; terms</strong> — pick the price book recorded on the packet, the valid-until date
                                                (empty = store default) and payment/delivery/lead-time terms and notes.
                                        </li>
                                        <li>
                                                <strong>Presentation</strong> — choose the document template: Trade Table (dense industrial price list),
                                                Visual Catalog (editorial, image-forward) or Technical Sheet (spec-driven). Paper size comes from Settings.
                                        </li>
                                        <li>
                                                <strong>Review &amp; publish</strong> — run preflight; blocking errors disable publishing. Publishing
                                                creates the immutable version, documents and buyer share link, shown with checksum and downloads.
                                        </li>
                                </ol>
                        </Card>

                        <Card title="Packet states" flush>
                                <div className="tss-card__pad tss-legend">
                                        {PACKET_LEGEND.map((entry) => (
                                                <div className="tss-legend__row" key={entry.status}>
                                                        <Badge tone={entry.tone}>{entry.status.replace(/_/g, ' ')}</Badge>
                                                        <span className="tss-dim">{entry.text}</span>
                                                </div>
                                        ))}
                                </div>
                        </Card>

                        <Card title="Quote states" flush>
                                <div className="tss-card__pad tss-legend">
                                        {QUOTE_LEGEND.map((entry) => (
                                                <div className="tss-legend__row" key={entry.status}>
                                                        <Badge tone={entry.tone}>{entry.status.replace(/_/g, ' ')}</Badge>
                                                        <span className="tss-dim">{entry.text}</span>
                                                </div>
                                        ))}
                                </div>
                        </Card>

                        <Card title="Documents & data">
                                <ul className="tss-helplist tss-helplist--bullets">
                                        <li>Every publish generates a PDF (per template), a CSV order sheet and a ZIP bundle, stored privately and streamed through permission-checked routes.</li>
                                        <li>Issued versions are immutable; corrections always create version N+1 via Revise, with prior versions marked superseded.</li>
                                        <li>Share links are token-gated and expire per the buyer token days setting; issuing a fresh link revokes the previous token.</li>
                                        <li>Technical documentation lives in the plugin repository’s <span className="tss-mono">docs/</span> folder (architecture and development notes), alongside the README.</li>
                                        <li>Quotes convert to WooCommerce orders at quoted prices using WooCommerce’s own tax engine; the conversion is idempotent per quote.</li>
                                </ul>
                                <div className="tss-wizard__actions">
                                        <Button variant="primary" onClick={() => go({ name: 'create' })}>Create a packet</Button>
                                        <Button onClick={() => go({ name: 'diagnostics' })}>Run diagnostics</Button>
                                </div>
                        </Card>

                        <Card title="Support note">
                                <p className="tss-dim">
                                        TradeSheet Studio is self-contained: no external services are contacted, prices are computed server-side and every
                                        mutation is audited in the event log. If something misbehaves, start with Diagnostics, then check the Recent
                                        activity feed on the Overview for the failing action. Background jobs (document generation, sweeps) run through
                                        Action Scheduler when available, with a WP-Cron fallback.
                                </p>
                        </Card>
                </div>
        );
}
