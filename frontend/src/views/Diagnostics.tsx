import type { ReactElement } from '../types/wp';
import { useResource } from '../hooks';
import { type ViewProps } from '../nav';
import { Button, Card, EmptyState, ErrorBanner, PageHeader, Skeleton } from '../components/ui';
import type { DiagnosticsResponse } from '../types/api';

export function DiagnosticsView(_props: ViewProps): ReactElement {
        const { data, loading, error, reload } = useResource<DiagnosticsResponse>('diagnostics');

        const okCount = data ? data.checks.filter((check) => check.ok).length : 0;
        const totalCount = data ? data.checks.length : 0;

        return (
                <div className="tss-view">
                        <PageHeader
                                title="Diagnostics"
                                subtitle={
                                        data
                                                ? `Plugin ${data.plugin_version} · schema ${data.schema_version || '—'} · ${okCount}/${totalCount} checks passing`
                                                : 'Live environment checks — nothing fabricated, every value comes from the server'
                                }
                                actions={<Button variant="ghost" onClick={reload} ariaLabel="Re-run diagnostics">Re-run</Button>}
                        />

                        <ErrorBanner message={error} onRetry={reload} />

                        {loading && !data ? (
                                <Card><Skeleton rows={8} /></Card>
                        ) : null}

                        {data ? (
                                <Card flush title={`Environment checks (${okCount}/${totalCount} ok)`}>
                                        {data.checks.length === 0 ? (
                                                <EmptyState title="No checks returned" hint="The diagnostics endpoint returned an empty list." />
                                        ) : (
                                                <div className="tss-diag">
                                                        {data.checks.map((check) => (
                                                                <div key={check.name} className={`tss-diag__row ${check.ok ? 'is-ok' : 'is-bad'}`}>
                                                                        <span className="tss-diag__mark" aria-hidden="true">{check.ok ? '✓' : '✕'}</span>
                                                                        <span className="tss-diag__label">
                                                                                {check.label}
                                                                                <span className="tss-diag__value">{check.value}</span>
                                                                        </span>
                                                                        <span className="tss-diag__advice">{check.advice}</span>
                                                                </div>
                                                        ))}
                                                </div>
                                        )}
                                </Card>
                        ) : null}

                        <p className="tss-note">
                                Failed background jobs, document storage permissions and the bundled PDF renderer are the most common culprits when
                                documents do not generate. The overview screen counts failed jobs in real time.
                        </p>
                </div>
        );
}
