import type { ReactElement } from '../types/wp';
import { formatDateTime, humanize } from '../format';
import { useResource } from '../hooks';
import { TEMPLATES, type ViewProps } from '../nav';
import { Button, Card, EmptyState, ErrorBanner, PageHeader, Skeleton } from '../components/ui';
import { currencySymbol } from '../api';
import type { DashboardMetrics, DashboardResponse, EventItem } from '../types/api';

interface MetricCard {
        key: keyof DashboardMetrics;
        label: string;
        hint: string;
        tone: 'teal' | 'amber' | 'green' | 'red' | 'neutral';
}

const METRICS: readonly MetricCard[] = [
        { key: 'awaiting', label: 'Awaiting action', hint: 'Packets submitted or in review', tone: 'amber' },
        { key: 'open_quotes', label: 'Open quotes', hint: 'Requested, in review or approved', tone: 'teal' },
        { key: 'expiring', label: 'Expiring offers', hint: 'Live packets valid ≤ 7 days', tone: 'amber' },
        { key: 'orders', label: 'Orders created', hint: 'Quotes converted to WooCommerce orders', tone: 'green' },
        { key: 'failed_jobs', label: 'Failed jobs', hint: 'Background jobs needing attention', tone: 'red' },
        { key: 'draft', label: 'Drafts', hint: 'Packets being composed', tone: 'neutral' },
];

function eventSummary(event: EventItem): string {
        let details = '';
        if (typeof event.details === 'string') {
                try {
                        const parsed: unknown = JSON.parse(event.details);
                        if (parsed && typeof parsed === 'object') {
                                details = objectSummary(parsed as Record<string, unknown>);
                        }
                } catch {
                        details = event.details;
                }
        } else if (event.details && typeof event.details === 'object') {
                details = objectSummary(event.details);
        }
        const base = `${humanize(event.action)} · ${event.object_type} #${event.object_id}`;
        return details === '' ? base : `${base} · ${details}`;
}

function objectSummary(details: Record<string, unknown>): string {
        const parts: string[] = [];
        for (const [key, value] of Object.entries(details)) {
                if (value === null || value === undefined || value === '') {
                        continue;
                }
                const text = typeof value === 'object' ? JSON.stringify(value) : String(value);
                parts.push(`${key}: ${text.length > 60 ? `${text.slice(0, 60)}…` : text}`);
                if (parts.length >= 3) {
                        break;
                }
        }
        return parts.join(', ');
}

export function OverviewView({ go }: ViewProps): ReactElement {
        const resource = useResource<DashboardResponse>('dashboard');
        const { data, loading, error, reload } = resource;

        return (
                <div className="tss-view">
                        <PageHeader
                                title="Overview"
                                subtitle={`Store currency: ${window.TSS_ADMIN.currency || '—'} (${currencySymbol() || '—'}) · plugin ${window.TSS_ADMIN.version}`}
                                actions={
                                        <span className="tss-pagehead__actions">
                                                <Button variant="primary" onClick={() => go({ name: 'create' })}>Create packet</Button>
                                                <Button onClick={() => go({ name: 'pricebooks' })}>Manage price books</Button>
                                                <Button variant="ghost" onClick={reload} ariaLabel="Refresh dashboard">Refresh</Button>
                                        </span>
                                }
                        />

                        <ErrorBanner message={error} onRetry={reload} />

                        {loading && !data ? (
                                <Card><Skeleton rows={4} /></Card>
                        ) : null}

                        {data ? (
                                <div className="tss-metric-grid">
                                        {METRICS.map((metric) => {
                                                const value = data.metrics[metric.key] ?? 0;
                                                return (
                                                        <div key={metric.key} className={`tss-metric tss-metric--${metric.tone}`}>
                                                                <span className="tss-metric__value">{String(value)}</span>
                                                                <span className="tss-metric__label">{metric.label}</span>
                                                                <span className="tss-metric__hint">{metric.hint}</span>
                                                        </div>
                                                );
                                        })}
                                </div>
                        ) : null}

                        {data ? (
                                <Card
                                        title="Recent activity"
                                        subtitle="Newest 25 audit events across packets, quotes, price books and documents"
                                        flush
                                >
                                        {data.events.length === 0 ? (
                                                <EmptyState
                                                        title="No activity yet"
                                                        hint="Events appear here once you create price books, compose packets, publish versions or handle quotes."
                                                />
                                        ) : (
                                                <ul className="tss-activity tss-admin-scroll">
                                                        {data.events.map((event) => (
                                                                <li key={event.id} className="tss-activity__item">
                                                                        <span className="tss-activity__time">{formatDateTime(event.created_at)}</span>
                                                                        <span className="tss-activity__text">{eventSummary(event)}</span>
                                                                        <span className="tss-activity__actor">{event.actor_id ? `by user #${event.actor_id}` : ''}</span>
                                                                </li>
                                                        ))}
                                                </ul>
                                        )}
                                </Card>
                        ) : null}

                        <Card title="Workflow cheat sheet" className="tss-overview__help">
                                <div className="tss-help-grid">
                                        <div>
                                                <h3>1 · Price books</h3>
                                                <p>Scoped customer/product rules feed packet pricing automatically.</p>
                                                <Button size="sm" onClick={() => go({ name: 'pricebooks' })}>Open price books</Button>
                                        </div>
                                        <div>
                                                <h3>2 · Compose</h3>
                                                <p>Five-step wizard: buyer, products, terms, presentation, preflight.</p>
                                                <Button size="sm" variant="primary" onClick={() => go({ name: 'create' })}>Create packet</Button>
                                        </div>
                                        <div>
                                                <h3>3 · Publish</h3>
                                                <p>Immutable version with checksum, documents and buyer share link.</p>
                                        </div>
                                        <div>
                                                <h3>4 · Quotes</h3>
                                                <p>Buyer submits quantities; integrity checks compare against the snapshot.</p>
                                                <Button size="sm" onClick={() => go({ name: 'quotes' })}>Open quotes</Button>
                                        </div>
                                        <div>
                                                <h3>5 · Order</h3>
                                                <p>Accepted quotes convert into WooCommerce orders at quoted prices.</p>
                                        </div>
                                        <div>
                                                <h3>Documents</h3>
                                                <p>{TEMPLATES.map((t) => t.label).join(' · ')} plus CSV and ZIP bundle.</p>
                                                <Button size="sm" variant="ghost" onClick={() => go({ name: 'help' })}>Full guide</Button>
                                        </div>
                                </div>
                        </Card>
                </div>
        );
}
