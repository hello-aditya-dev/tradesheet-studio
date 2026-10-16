/**
 * Application shell: sidebar navigation + view switcher. All state is
 * internal (no routing libraries); `go()` swaps the active view object from
 * nav.ts and scrolls the main region back to the top.
 */
import type { ReactElement } from './types/wp';
import { useCallback, useEffect, useRef, useState } from './wp';
import { bootstrap } from './api';
import { type View, type ViewProps } from './nav';
import { ConfirmHost } from './components/confirm';
import { ToastHost } from './components/toast';
import { Badge, cx } from './components/ui';
import { OverviewView } from './views/Overview';
import { PacketsView } from './views/Packets';
import { PacketDetailView } from './views/PacketDetail';
import { PacketCreateView } from './views/PacketCreate';
import { PriceBooksView } from './views/PriceBooks';
import { PriceBookDetailView } from './views/PriceBooks';
import { QuotesView } from './views/Quotes';
import { QuoteDetailView } from './views/Quotes';
import { CustomersView } from './views/Customers';
import { SettingsView } from './views/Settings';
import { DiagnosticsView } from './views/Diagnostics';
import { HelpView } from './views/Help';

interface NavItem {
        label: string;
        view: View;
        matches: (view: View) => boolean;
}

interface NavGroup {
        title: string;
        items: readonly NavItem[];
}

const sameName = (name: View['name']) => (view: View): boolean => view.name === name;

const NAV_GROUPS: readonly NavGroup[] = [
        {
                title: 'Deal pipeline',
                items: [
                        { label: 'Overview', view: { name: 'overview' }, matches: sameName('overview') },
                        { label: 'Sales packets', view: { name: 'packets' }, matches: (v) => v.name === 'packets' || v.name === 'packet' || v.name === 'create' },
                        { label: 'Create packet', view: { name: 'create' }, matches: () => false },
                        { label: 'Quotes', view: { name: 'quotes' }, matches: (v) => v.name === 'quotes' || v.name === 'quote' },
                ],
        },
        {
                title: 'Pricing',
                items: [
                        { label: 'Price books', view: { name: 'pricebooks' }, matches: (v) => v.name === 'pricebooks' || v.name === 'pricebook' },
                        { label: 'Customers', view: { name: 'customers' }, matches: sameName('customers') },
                ],
        },
        {
                title: 'System',
                items: [
                        { label: 'Settings', view: { name: 'settings' }, matches: sameName('settings') },
                        { label: 'Diagnostics', view: { name: 'diagnostics' }, matches: sameName('diagnostics') },
                        { label: 'Help', view: { name: 'help' }, matches: sameName('help') },
                ],
        },
];

export function App(): ReactElement {
        const [view, setView] = useState<View>({ name: 'overview' });
        const mainRef = useRef<HTMLElement | null>(null);

        const go = useCallback((next: View): void => {
                setView(next);
        }, []);

        useEffect(() => {
                if (mainRef.current) {
                        mainRef.current.scrollTop = 0;
                }
                window.scrollTo(0, 0);
        }, [view]);

        const props: ViewProps = { go };
        const cfg = bootstrap();

        let body: ReactElement;
        switch (view.name) {
                case 'packets':
                        body = <PacketsView {...props} />;
                        break;
                case 'packet':
                        body = <PacketDetailView {...props} id={view.id} />;
                        break;
                case 'create':
                        body = <PacketCreateView {...props} resumeId={view.resumeId} />;
                        break;
                case 'pricebooks':
                        body = <PriceBooksView {...props} />;
                        break;
                case 'pricebook':
                        body = <PriceBookDetailView {...props} id={view.id} />;
                        break;
                case 'quotes':
                        body = <QuotesView {...props} />;
                        break;
                case 'quote':
                        body = <QuoteDetailView {...props} id={view.id} />;
                        break;
                case 'customers':
                        body = <CustomersView {...props} />;
                        break;
                case 'settings':
                        body = <SettingsView {...props} />;
                        break;
                case 'diagnostics':
                        body = <DiagnosticsView {...props} />;
                        break;
                case 'help':
                        body = <HelpView {...props} />;
                        break;
                case 'overview':
                default:
                        body = <OverviewView {...props} />;
                        break;
        }

        return (
                <div className="tss-admin">
                        <aside className="tss-sidebar">
                                <div className="tss-sidebar__brand">
                                        <span className="tss-sidebar__logo" aria-hidden="true">TS</span>
                                        <span className="tss-sidebar__brand-text">
                                                <span className="tss-sidebar__title">TradeSheet Studio</span>
                                                <span className="tss-sidebar__sub">DealPacket cockpit</span>
                                        </span>
                                </div>

                                <nav className="tss-sidebar__nav" aria-label="TradeSheet Studio sections">
                                        {NAV_GROUPS.map((group) => (
                                                <div className="tss-sidebar__group" key={group.title}>
                                                        <p className="tss-sidebar__group-title">{group.title}</p>
                                                        <ul className="tss-sidebar__list">
                                                                {group.items.map((item) => {
                                                                        const active = item.matches(view);
                                                                        return (
                                                                                <li key={item.label}>
                                                                                        <button
                                                                                                type="button"
                                                                                                className={cx('tss-sidebar__item', active && 'is-active')}
                                                                                                onClick={() => go(item.view)}
                                                                                                aria-current={active ? 'page' : undefined}
                                                                                        >
                                                                                                {item.label}
                                                                                        </button>
                                                                                </li>
                                                                        );
                                                                })}
                                                        </ul>
                                                </div>
                                        ))}
                                </nav>

                                <div className="tss-sidebar__foot">
                                        <p className="tss-sidebar__foot-line">
                                                v{cfg.version} · {cfg.currency || '—'} <span className="tss-sidebar__cur">{cfg.currencySymbol}</span>
                                        </p>
                                        <p className="tss-sidebar__foot-line tss-dim">
                                                {cfg.pdfRenderer ? <Badge tone="green">PDF ready</Badge> : <Badge tone="red">PDF renderer missing</Badge>}
                                        </p>
                                </div>
                        </aside>

                        <main className="tss-main" ref={mainRef}>
                                {body}
                        </main>

                        <ToastHost />
                        <ConfirmHost />
                </div>
        );
}
