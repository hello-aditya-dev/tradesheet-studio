import type { ReactElement } from '../types/wp';
import { useEffect, useState } from '../wp';

export type ToastKind = 'success' | 'error' | 'info';

export interface ToastItem {
        id: number;
        kind: ToastKind;
        text: string;
}

type Listener = (items: ToastItem[]) => void;

const listeners = new Set<Listener>();
let items: ToastItem[] = [];
let seq = 1;

function emit(): void {
        for (const listener of listeners) {
                listener(items);
        }
}

function dismiss(id: number): void {
        items = items.filter((t) => t.id !== id);
        emit();
}

function push(kind: ToastKind, text: string, ttl: number): void {
        const item: ToastItem = { id: seq++, kind, text };
        items = [...items.slice(-4), item];
        emit();
        window.setTimeout(() => dismiss(item.id), ttl);
}

export const toast = {
        success: (text: string): void => push('success', text, 4000),
        error: (text: string): void => push('error', text, 8000),
        info: (text: string): void => push('info', text, 5000),
};

export function ToastHost(): ReactElement {
        const [list, setList] = useState<ToastItem[]>(items);

        useEffect(() => {
                const listener: Listener = (next) => setList(next);
                listeners.add(listener);
                return () => {
                        listeners.delete(listener);
                };
        }, []);

        return (
                <div className="tss-toasts" role="status" aria-live="polite">
                        {list.map((t) => (
                                <div key={t.id} className={`tss-toast tss-toast--${t.kind}`}>
                                        <span className="tss-toast__dot" aria-hidden="true" />
                                        <span className="tss-toast__text">{t.text}</span>
                                        <button
                                                type="button"
                                                className="tss-toast__close"
                                                onClick={() => dismiss(t.id)}
                                                aria-label="Dismiss notification"
                                        >
                                                ×
                                        </button>
                                </div>
                        ))}
                </div>
        );
}
