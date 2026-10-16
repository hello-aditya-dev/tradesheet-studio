/**
 * Shared packet mutation helpers used by both the packets table and the
 * packet detail view (confirm dialogs + toasts included).
 */
import { apiDelete, apiDownload, apiGet, apiPost } from './api';
import { confirm } from './components/confirm';
import { toast } from './components/toast';
import { copyToClipboard } from './components/ui';
import type { PacketDetail } from './types/api';

export async function doShareLink(id: number): Promise<void> {
        const share = await apiGet<Share>(`packets/${id}/share`);
        const ok = await copyToClipboard(share.url);
        if (ok) {
                toast.success(`Share link copied — valid until ${share.expires_at.slice(0, 10)}.`);
        } else {
                toast.error('Could not copy to the clipboard; open the packet and copy the link manually.');
        }
}

interface Share {
        url: string;
        token: string;
        expires_at: string;
}

/**
 * Download a packet document. PDF documents are stored per template, so the
 * template is resolved from the packet's document list first; CSV/ZIP
 * documents are stored without a template.
 */
export async function doDownloadDoc(id: number, type: 'pdf' | 'csv' | 'zip', ref: string): Promise<void> {
        let template = '';
        if (type === 'pdf') {
                const detail = await apiGet<PacketDetail>(`packets/${id}`);
                const pdf = detail.documents.find((d) => d.type === 'pdf');
                if (!pdf) {
                        toast.error('No PDF document exists for the current version yet. Regenerate documents first.');
                        return;
                }
                template = pdf.template;
        }
        const query = `type=${type}${type === 'pdf' ? `&template=${encodeURIComponent(template)}` : ''}`;
        const ext = type === 'pdf' ? 'pdf' : type === 'csv' ? 'csv' : 'zip';
        await apiDownload(`packets/${id}/download?${query}`, `tss-${ref}.${ext}`);
        toast.success(`${type.toUpperCase()} download started.`);
}

export async function doRevise(id: number): Promise<boolean> {
        const proceed = await confirm({
                title: 'Start a revision?',
                message:
                        'The current version is copied into a new editable draft. The issued version stays immutable and keeps serving buyers until you publish the next version.',
                confirmLabel: 'Start revision',
        });
        if (!proceed) {
                return false;
        }
        await apiPost('packets/' + id + '/revise');
        toast.success('Revision draft created from the current version.');
        return true;
}

export async function doTransition(id: number, action: 'revoke' | 'expire' | 'archive' | 'cancel'): Promise<boolean> {
        const copy: Record<string, { title: string; message: string; label: string }> = {
                revoke: {
                        title: 'Revoke this packet?',
                        message:
                                'Buyer access is revoked immediately (existing share tokens are invalidated) and the packet becomes revoked. This cannot be undone.',
                        label: 'Revoke packet',
                },
                expire: {
                        title: 'Expire this packet now?',
                        message:
                                'The packet is marked expired and stops accepting quote requests, exactly as if its validity date had passed. This cannot be undone.',
                        label: 'Expire packet',
                },
                archive: {
                        title: 'Archive this packet?',
                        message:
                                'The packet moves out of the active pipeline but stays queryable with its full version and event history for audit.',
                        label: 'Archive packet',
                },
                cancel: {
                        title: 'Cancel this packet?',
                        message:
                                'The offer is withdrawn from the buyer portal and the packet becomes cancelled. This cannot be undone.',
                        label: 'Cancel packet',
                },
        };
        const spec = copy[action];
        const proceed = await confirm({ title: spec.title, message: spec.message, confirmLabel: spec.label, danger: true });
        if (!proceed) {
                return false;
        }
        await apiPost(`packets/${id}/${action}`);
        toast.success(`Packet ${action === 'archive' ? 'archived' : action === 'expire' ? 'marked expired' : action + 'd'}.`);
        return true;
}

export async function doDeleteDraft(id: number): Promise<boolean> {
        const proceed = await confirm({
                title: 'Delete this draft?',
                message: 'The draft packet and its lines are removed permanently. Issued packets must be revoked or archived instead.',
                confirmLabel: 'Delete draft',
                danger: true,
        });
        if (!proceed) {
                return false;
        }
        await apiDelete(`packets/${id}`);
        toast.success('Draft packet deleted.');
        return true;
}

/** Download a specific stored document (type + template both known). */
export async function doDownloadDocExplicit(id: number, ref: string, type: string, template: string): Promise<void> {
	const query = `type=${encodeURIComponent(type)}${template !== '' ? `&template=${encodeURIComponent(template)}` : ''}`;
	const ext = type === 'pdf' ? 'pdf' : type === 'csv' ? 'csv' : 'zip';
	await apiDownload(`packets/${id}/download?${query}`, `tss-${ref}.${ext}`);
	toast.success(`${type.toUpperCase()} download started.`);
}
