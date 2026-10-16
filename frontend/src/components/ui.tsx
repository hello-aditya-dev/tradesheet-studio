/**
 * Scoped, keyboard-accessible component kit for the TradeSheet Studio admin
 * app. Deliberately NOT @wordpress/components: everything here is plain
 * elements styled under `.tss-admin-*` classes so the dark teal/amber theme
 * stays fully controlled. React itself comes from window.wp.element.
 */
import type {
        ChangeEvent as ChangeEventT,
        FocusEvent as FocusEventT,
        KeyboardEvent as KeyboardEventT,
        MouseEvent as MouseEventT,
        ReactElement,
        ReactNode,
} from '../types/wp';
import { useEffect, useRef, useState } from '../wp';
import { toast } from './toast';

export function cx(...parts: readonly (string | false | null | undefined)[]): string {
        return parts.filter((p): p is string => typeof p === 'string' && p !== '').join(' ');
}

/* ------------------------------------------------------------------ Button */

export type ButtonVariant = 'primary' | 'secondary' | 'danger' | 'ghost' | 'amber';

export interface ButtonProps {
        children: ReactNode;
        onClick?: (e: MouseEventT<HTMLButtonElement>) => void;
        variant?: ButtonVariant;
        size?: 'sm' | 'md' | 'lg';
        type?: 'button' | 'submit';
        disabled?: boolean;
        busy?: boolean;
        title?: string;
        className?: string;
        ariaLabel?: string;
}

export function Button(props: ButtonProps): ReactElement {
        const {
                children, onClick, variant = 'secondary', size = 'md',
                type = 'button', disabled = false, busy = false, title, className, ariaLabel,
        } = props;
        return (
                <button
                        type={type}
                        className={cx('tss-btn', `tss-btn--${variant}`, `tss-btn--${size}`, busy && 'is-busy', className)}
                        onClick={onClick}
                        disabled={disabled || busy}
                        title={title}
                        aria-label={ariaLabel}
                >
                        {busy ? <span className="tss-btn__spinner" aria-hidden="true" /> : null}
                        <span className="tss-btn__label">{children}</span>
                </button>
        );
}

/* ------------------------------------------------------------------ Badge */

export type Tone = 'neutral' | 'teal' | 'amber' | 'green' | 'red' | 'gray';

export interface BadgeProps {
        children: ReactNode;
        tone?: Tone;
        title?: string;
        className?: string;
}

export function Badge(props: BadgeProps): ReactElement {
        const { children, tone = 'neutral', title, className } = props;
        return (
                <span className={cx('tss-badge', `tss-badge--${tone}`, className)} title={title}>
                        {children}
                </span>
        );
}

export type PacketStatusTone = { tone: Tone; label: string };

export function packetStatus(status: string): PacketStatusTone {
        switch (status) {
                case 'draft':
                        return { tone: 'neutral', label: 'Draft' };
                case 'issued':
                        return { tone: 'amber', label: 'Issued' };
                case 'submitted':
                        return { tone: 'amber', label: 'Submitted' };
                case 'in_review':
                        return { tone: 'amber', label: 'In review' };
                case 'approved':
                        return { tone: 'green', label: 'Approved' };
                case 'accepted':
                        return { tone: 'green', label: 'Accepted' };
                case 'ordered':
                        return { tone: 'green', label: 'Ordered' };
                case 'expired':
                        return { tone: 'red', label: 'Expired' };
                case 'revoked':
                        return { tone: 'red', label: 'Revoked' };
                case 'cancelled':
                        return { tone: 'red', label: 'Cancelled' };
                case 'archived':
                        return { tone: 'gray', label: 'Archived' };
                default:
                        return { tone: 'neutral', label: status };
        }
}

export function quoteStatus(status: string): PacketStatusTone {
        switch (status) {
                case 'requested':
                        return { tone: 'amber', label: 'Requested' };
                case 'in_review':
                        return { tone: 'amber', label: 'In review' };
                case 'revised':
                        return { tone: 'teal', label: 'Revised' };
                case 'approved':
                        return { tone: 'green', label: 'Approved' };
                case 'accepted':
                        return { tone: 'green', label: 'Accepted' };
                case 'ordered':
                        return { tone: 'green', label: 'Ordered' };
                case 'rejected':
                        return { tone: 'red', label: 'Rejected' };
                case 'superseded':
                        return { tone: 'gray', label: 'Superseded' };
                case 'expired':
                        return { tone: 'red', label: 'Expired' };
                case 'cancelled':
                        return { tone: 'gray', label: 'Cancelled' };
                default:
                        return { tone: 'neutral', label: status };
        }
}

export function PacketStatusBadge({ status }: { status: string }): ReactElement {
        const meta = packetStatus(status);
        return <Badge tone={meta.tone} title={status}>{meta.label}</Badge>;
}

export function QuoteStatusBadge({ status }: { status: string }): ReactElement {
        const meta = quoteStatus(status);
        return <Badge tone={meta.tone} title={status}>{meta.label}</Badge>;
}

/* ------------------------------------------------------------------- Card */

export interface CardProps {
        children: ReactNode;
        title?: ReactNode;
        subtitle?: ReactNode;
        actions?: ReactNode;
        className?: string;
        flush?: boolean;
}

export function Card(props: CardProps): ReactElement {
        const { children, title, subtitle, actions, className, flush = false } = props;
        return (
                <section className={cx('tss-card', flush && 'tss-card--flush', className)}>
                        {title !== undefined || actions !== undefined ? (
                                <header className="tss-card__head">
                                        <div className="tss-card__head-text">
                                                {title !== undefined ? <h2 className="tss-card__title">{title}</h2> : null}
                                                {subtitle !== undefined ? <p className="tss-card__subtitle">{subtitle}</p> : null}
                                        </div>
                                        {actions !== undefined ? <div className="tss-card__actions">{actions}</div> : null}
                                </header>
                        ) : null}
                        <div className={cx('tss-card__body', flush && 'tss-card__body--flush')}>{children}</div>
                </section>
        );
}

/* -------------------------------------------------------- Loading / error */

export function Spinner({ className }: { className?: string }): ReactElement {
        return <span className={cx('tss-spinner', className)} role="progressbar" aria-label="Loading" />;
}

export function Skeleton({ rows = 3, className }: { rows?: number; className?: string }): ReactElement {
        const widths = [92, 76, 84, 68, 60];
        return (
                <div className={cx('tss-skeleton', className)} aria-hidden="true">
                        {Array.from({ length: rows }, (_, i) => (
                                <div key={i} className="tss-skeleton__row" style={{ width: `${widths[i % widths.length]}%` }} />
                        ))}
                </div>
        );
}

export function ErrorBanner({ message, onRetry }: { message: string | null; onRetry?: () => void }): ReactElement | null {
        if (!message) {
                return null;
        }
        return (
                <div className="tss-error-banner" role="alert">
                        <span className="tss-error-banner__mark" aria-hidden="true">!</span>
                        <span className="tss-error-banner__text">{message}</span>
                        {onRetry ? (
                                <button type="button" className="tss-error-banner__retry" onClick={onRetry}>
                                        Retry
                                </button>
                        ) : null}
                </div>
        );
}

export function EmptyState(props: { title: string; hint?: ReactNode; children?: ReactNode }): ReactElement {
        const { title, hint, children } = props;
        return (
                <div className="tss-empty">
                        <p className="tss-empty__title">{title}</p>
                        {hint ? <p className="tss-empty__hint">{hint}</p> : null}
                        {children ? <div className="tss-empty__actions">{children}</div> : null}
                </div>
        );
}

/* ------------------------------------------------------------------ Modal */

export interface ModalProps {
        title: string;
        onClose: () => void;
        children: ReactNode;
        wide?: boolean;
}

export function Modal(props: ModalProps): ReactElement {
        const { title, onClose, children, wide = false } = props;
        const panel = useRef<HTMLDivElement | null>(null);

        useEffect(() => {
                const onKey = (e: KeyboardEvent): void => {
                        if (e.key === 'Escape') {
                                onClose();
                        }
                };
                document.addEventListener('keydown', onKey);
                const previousActive = document.activeElement instanceof HTMLElement ? document.activeElement : null;
                panel.current?.focus();
                return () => {
                        document.removeEventListener('keydown', onKey);
                        previousActive?.focus();
                };
        }, [onClose]);

        return (
                <div className="tss-modal-overlay" onMouseDown={(e: MouseEventT<HTMLDivElement>) => {
                        if (e.target === e.currentTarget) {
                                onClose();
                        }
                }}>
                        <div
                                className={cx('tss-modal', wide && 'tss-modal--wide')}
                                role="dialog"
                                aria-modal="true"
                                aria-label={title}
                                tabIndex={-1}
                                ref={panel}
                        >
                                <header className="tss-modal__head">
                                        <h2 className="tss-modal__title">{title}</h2>
                                        <button type="button" className="tss-modal__close" onClick={onClose} aria-label="Close dialog">
                                                ×
                                        </button>
                                </header>
                                <div className="tss-modal__body">{children}</div>
                        </div>
                </div>
        );
}

/* ------------------------------------------------------------------ Forms */

export interface FieldProps {
        label: string;
        hint?: ReactNode;
        children: ReactNode;
        required?: boolean;
        className?: string;
}

export function Field(props: FieldProps): ReactElement {
        const { label, hint, children, required = false, className } = props;
        return (
                <label className={cx('tss-field', className)}>
                        <span className="tss-field__label">
                                {label}
                                {required ? <span className="tss-field__req" aria-hidden="true"> *</span> : null}
                        </span>
                        {children}
                        {hint ? <span className="tss-field__hint">{hint}</span> : null}
                </label>
        );
}

export interface TextInputProps {
        value: string;
        onChange: (value: string) => void;
        type?: 'text' | 'number' | 'date' | 'email' | 'password' | 'url';
        placeholder?: string;
        disabled?: boolean;
        mono?: boolean;
        autoFocus?: boolean;
        min?: number;
        max?: number;
        step?: number;
        maxLength?: number;
        className?: string;
        onEnter?: () => void;
        ariaLabel?: string;
}

export function TextInput(props: TextInputProps): ReactElement {
        const {
                value, onChange, type = 'text', placeholder, disabled = false, mono = false,
                autoFocus = false, min, max, step, maxLength, className, onEnter, ariaLabel,
        } = props;
        return (
                <input
                        className={cx('tss-input', mono && 'tss-input--mono', className)}
                        type={type}
                        value={value}
                        placeholder={placeholder}
                        disabled={disabled}
                        autoFocus={autoFocus}
                        min={min}
                        max={max}
                        step={step}
                        maxLength={maxLength}
                        aria-label={ariaLabel}
                        onChange={(e: ChangeEventT<HTMLInputElement>) => onChange(e.target.value)}
                        onKeyDown={(e: KeyboardEventT<HTMLInputElement>) => {
                                if (onEnter && e.key === 'Enter') {
                                        onEnter();
                                }
                        }}
                />
        );
}

export interface TextAreaProps {
        value: string;
        onChange: (value: string) => void;
        rows?: number;
        placeholder?: string;
        disabled?: boolean;
        autoFocus?: boolean;
        className?: string;
        ariaLabel?: string;
}

export function TextArea(props: TextAreaProps): ReactElement {
        const { value, onChange, rows = 3, placeholder, disabled = false, autoFocus = false, className, ariaLabel } = props;
        return (
                <textarea
                        className={cx('tss-textarea', className)}
                        rows={rows}
                        value={value}
                        placeholder={placeholder}
                        disabled={disabled}
                        autoFocus={autoFocus}
                        aria-label={ariaLabel}
                        onChange={(e: ChangeEventT<HTMLTextAreaElement>) => onChange(e.target.value)}
                />
        );
}

export interface SelectProps {
        value: string;
        onChange: (value: string) => void;
        children: ReactNode;
        disabled?: boolean;
        className?: string;
        ariaLabel?: string;
}

export function Select(props: SelectProps): ReactElement {
        const { value, onChange, children, disabled = false, className, ariaLabel } = props;
        return (
                <select
                        className={cx('tss-select', className)}
                        value={value}
                        disabled={disabled}
                        aria-label={ariaLabel}
                        onChange={(e: ChangeEventT<HTMLSelectElement>) => onChange(e.target.value)}
                >
                        {children}
                </select>
        );
}

export interface ToggleProps {
        checked: boolean;
        onChange: (checked: boolean) => void;
        label: string;
        hint?: ReactNode;
        danger?: boolean;
}

export function Toggle(props: ToggleProps): ReactElement {
        const { checked, onChange, label, hint, danger = false } = props;
        return (
                <label className={cx('tss-toggle', danger && 'tss-toggle--danger')}>
                        <span className="tss-toggle__control">
                                <input
                                        type="checkbox"
                                        checked={checked}
                                        onChange={(e: ChangeEventT<HTMLInputElement>) => onChange(e.target.checked)}
                                />
                                <span className="tss-toggle__track" aria-hidden="true" />
                        </span>
                        <span className="tss-toggle__text">
                                <span className="tss-toggle__label">{label}</span>
                                {hint ? <span className="tss-toggle__hint">{hint}</span> : null}
                        </span>
                </label>
        );
}

export function SearchInput(props: {
        value: string;
        onChange: (value: string) => void;
        placeholder?: string;
        autoFocus?: boolean;
        ariaLabel?: string;
}): ReactElement {
        const { value, onChange, placeholder = 'Search…', autoFocus = false, ariaLabel } = props;
        return (
                <div className="tss-search">
                        <span className="tss-search__icon" aria-hidden="true">&#8981;</span>
                        <input
                                className="tss-search__input"
                                type="text"
                                value={value}
                                placeholder={placeholder}
                                autoFocus={autoFocus}
                                aria-label={ariaLabel ?? placeholder}
                                onChange={(e: ChangeEventT<HTMLInputElement>) => onChange(e.target.value)}
                        />
                        {value !== '' ? (
                                <button type="button" className="tss-search__clear" onClick={() => onChange('')} aria-label="Clear search">
                                        ×
                                </button>
                        ) : null}
                </div>
        );
}

/* ------------------------------------------------------------- Pagination */

export interface PaginationProps {
        page: number;
        hasPrev: boolean;
        hasNext: boolean;
        onPage: (page: number) => void;
        total?: number | null;
        per_page?: number;
        label?: string;
}

export function Pagination(props: PaginationProps): ReactElement {
        const { page, hasPrev, hasNext, onPage, total = null, per_page, label = 'items' } = props;
        const rangeStart = total === null ? null : total === 0 ? 0 : (page - 1) * (per_page ?? 0) + 1;
        const rangeEnd = total === null ? null : Math.min(total, page * (per_page ?? 0));
        return (
                <div className="tss-pagination">
                        <span className="tss-pagination__info">
                                {total !== null && per_page
                                        ? `${rangeStart}–${rangeEnd} of ${total} ${label}`
                                        : `Page ${page}`}
                        </span>
                        <span className="tss-pagination__controls">
                                <Button size="sm" disabled={!hasPrev} onClick={() => onPage(page - 1)} ariaLabel="Previous page">
                                        ‹ Prev
                                </Button>
                                <Button size="sm" disabled={!hasNext} onClick={() => onPage(page + 1)} ariaLabel="Next page">
                                        Next ›
                                </Button>
                        </span>
                </div>
        );
}

/* ------------------------------------------------------------------ Copy */

export async function copyToClipboard(text: string): Promise<boolean> {
        try {
                if (navigator.clipboard && window.isSecureContext) {
                        await navigator.clipboard.writeText(text);
                        return true;
                }
        } catch {
                // fall through to the legacy path
        }
        try {
                const area = document.createElement('textarea');
                area.value = text;
                area.setAttribute('readonly', 'readonly');
                area.style.position = 'fixed';
                area.style.left = '-9999px';
                document.body.appendChild(area);
                area.select();
                const ok = document.execCommand('copy');
                area.remove();
                return ok;
        } catch {
                return false;
        }
}

export function CopyButton(props: { value: string; label?: string; className?: string }): ReactElement {
        const { value, label = 'Copy', className } = props;
        const [done, setDone] = useState(false);
        return (
                <Button
                        size="sm"
                        variant="secondary"
                        className={className}
                        onClick={() => {
                                copyToClipboard(value).then((ok) => {
                                        if (ok) {
                                                setDone(true);
                                                window.setTimeout(() => setDone(false), 1800);
                                                toast.success('Copied to clipboard.');
                                        } else {
                                                toast.error('Could not access the clipboard — copy the text manually.');
                                        }
                                });
                        }}
                >
                        {done ? 'Copied' : label}
                </Button>
        );
}

export function CopyField(props: { value: string; label?: string }): ReactElement {
        const { value, label } = props;
        return (
                <div className="tss-copyfield">
                        {label ? <span className="tss-copyfield__label">{label}</span> : null}
                        <input
                                className="tss-copyfield__value"
                                type="text"
                                readOnly
                                value={value}
                                onFocus={(e: FocusEventT<HTMLInputElement>) => e.target.select()}
                        />
                        <CopyButton value={value} />
                </div>
        );
}

/* ---------------------------------------------------------------- RowMenu */

export interface MenuItem {
        label: string;
        onClick: () => void;
        disabled?: boolean;
        danger?: boolean;
        divider?: boolean;
}

export function RowMenu(props: { items: MenuItem[]; ariaLabel?: string }): ReactElement {
        const { items, ariaLabel = 'Row actions' } = props;
        const [open, setOpen] = useState(false);
        const root = useRef<HTMLDivElement | null>(null);

        useEffect(() => {
                if (!open) {
                        return;
                }
                const onDoc = (e: Event): void => {
                        if (root.current && e.target instanceof Node && !root.current.contains(e.target)) {
                                setOpen(false);
                        }
                };
                const onKey = (e: KeyboardEvent): void => {
                        if (e.key === 'Escape') {
                                setOpen(false);
                        }
                };
                document.addEventListener('mousedown', onDoc);
                document.addEventListener('keydown', onKey);
                return () => {
                        document.removeEventListener('mousedown', onDoc);
                        document.removeEventListener('keydown', onKey);
                };
        }, [open]);

        return (
                <div className="tss-rowmenu" ref={root}>
                        <button
                                type="button"
                                className={cx('tss-rowmenu__trigger', open && 'is-open')}
                                aria-haspopup="menu"
                                aria-expanded={open}
                                aria-label={ariaLabel}
                                onClick={() => setOpen((v) => !v)}
                        >
                                ⋯
                        </button>
                        {open ? (
                                <div className="tss-rowmenu__menu" role="menu">
                                        {items.map((item, i) =>
                                                item.divider ? (
                                                        <div key={`div-${i}`} className="tss-rowmenu__divider" role="separator" />
                                                ) : (
                                                        <button
                                                                key={item.label}
                                                                type="button"
                                                                role="menuitem"
                                                                className={cx('tss-rowmenu__item', item.danger && 'is-danger')}
                                                                disabled={item.disabled}
                                                                onClick={() => {
                                                                        setOpen(false);
                                                                        item.onClick();
                                                                }}
                                                        >
                                                                {item.label}
                                                        </button>
                                                ),
                                        )}
                                </div>
                        ) : null}
                </div>
        );
}

/* ------------------------------------------------------------- Meta lists */

export interface MetaRow {
        label: string;
        value: ReactNode;
}

export function MetaList({ rows }: { rows: readonly MetaRow[] }): ReactElement {
        return (
                <dl className="tss-meta">
                        {rows.map((row) => (
                                <div className="tss-meta__row" key={row.label}>
                                        <dt className="tss-meta__label">{row.label}</dt>
                                        <dd className="tss-meta__value">{row.value}</dd>
                                </div>
                        ))}
                </dl>
        );
}

/* ------------------------------------------------------------- Steps rail */

export function StepIndicator({ steps, current }: { steps: readonly string[]; current: number }): ReactElement {
        return (
                <ol className="tss-steps">
                        {steps.map((label, i) => {
                                const n = i + 1;
                                const state = n < current ? 'done' : n === current ? 'current' : 'todo';
                                return (
                                        <li key={label} className={cx('tss-steps__item', `is-${state}`)}>
                                                <span className="tss-steps__dot" aria-hidden="true">{n < current ? '✓' : n}</span>
                                                <span className="tss-steps__label">{label}</span>
                                        </li>
                                );
                        })}
                </ol>
        );
}

/* ------------------------------------------------------------- Page header */

export interface PageHeaderProps {
        title: ReactNode;
        subtitle?: ReactNode;
        actions?: ReactNode;
        back?: { label: string; onClick: () => void };
}

export function PageHeader(props: PageHeaderProps): ReactElement {
        const { title, subtitle, actions, back } = props;
        return (
                <header className="tss-pagehead">
                        <div className="tss-pagehead__text">
                                {back ? (
                                        <button type="button" className="tss-pagehead__back" onClick={back.onClick}>
                                                ‹ {back.label}
                                        </button>
                                ) : null}
                                <h1 className="tss-pagehead__title">{title}</h1>
                                {subtitle ? <p className="tss-pagehead__subtitle">{subtitle}</p> : null}
                        </div>
                        {actions ? <div className="tss-pagehead__actions">{actions}</div> : null}
                </header>
        );
}
