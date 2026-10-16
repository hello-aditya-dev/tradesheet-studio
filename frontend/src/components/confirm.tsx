import type { ReactElement } from '../types/wp';
import { useEffect, useRef, useState } from '../wp';
import { Button, Modal } from './ui';

export interface ConfirmRequest {
        title: string;
        message: string;
        confirmLabel?: string;
        cancelLabel?: string;
        danger?: boolean;
}

type Resolver = (proceed: boolean) => void;

let handler: ((req: ConfirmRequest) => Promise<boolean>) | null = null;

/**
 * Promise-based confirmation for destructive/irreversible actions
 * (publish, revoke, delete, revise, convert…). Falls back to the native
 * confirm() if the host is not mounted.
 */
export function confirm(req: ConfirmRequest): Promise<boolean> {
        if (handler) {
                return handler(req);
        }
        return Promise.resolve(window.confirm(`${req.title}\n\n${req.message}`));
}

export function ConfirmHost(): ReactElement | null {
        const [req, setReq] = useState<ConfirmRequest | null>(null);
        const resolver = useRef<Resolver | null>(null);

        useEffect(() => {
                handler = (r: ConfirmRequest): Promise<boolean> =>
                        new Promise<boolean>((resolve) => {
                                resolver.current?.(false);
                                resolver.current = resolve;
                                setReq(r);
                        });
                return () => {
                        handler = null;
                        resolver.current?.(false);
                        resolver.current = null;
                };
        }, []);

        const settle = (proceed: boolean): void => {
                resolver.current?.(proceed);
                resolver.current = null;
                setReq(null);
        };

        if (!req) {
                return null;
        }

        return (
                <Modal title={req.title} onClose={() => settle(false)}>
                        <p className="tss-confirm__message">{req.message}</p>
                        <div className="tss-modal__footer">
                                <Button variant="secondary" onClick={() => settle(false)}>
                                        {req.cancelLabel ?? 'Cancel'}
                                </Button>
                                <Button variant={req.danger ? 'danger' : 'primary'} onClick={() => settle(true)}>
                                        {req.confirmLabel ?? 'Confirm'}
                                </Button>
                        </div>
                </Modal>
        );
}
