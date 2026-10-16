import type { ReactElement } from '../types/wp';
import { useEffect, useState } from '../wp';
import { apiPost, errorMessage } from '../api';
import { useResource } from '../hooks';
import { TEMPLATES, type ViewProps } from '../nav';
import { confirm } from '../components/confirm';
import { toast } from '../components/toast';
import {
        Button, Card, ErrorBanner, Field, PageHeader, Select, Skeleton, TextArea, TextInput, Toggle,
} from '../components/ui';
import type { Settings, SettingsResponse } from '../types/api';

const DOC_SIZES: readonly { id: string; label: string }[] = [
        { id: 'a4', label: 'A4 (210 × 297 mm)' },
        { id: 'letter', label: 'US Letter (8.5 × 11 in)' },
];

const NUM_FIELDS: readonly { key: keyof Settings; label: string; hint: string; min: number; max: number }[] = [
        { key: 'default_validity_days', label: 'Default packet validity (days)', hint: 'Used when a packet has no explicit valid-until date (1–730).', min: 1, max: 730 },
        { key: 'quote_validity_days', label: 'Quote validity (days)', hint: 'How long a quote request or revision stays acceptable (1–730).', min: 1, max: 730 },
        { key: 'buyer_token_days', label: 'Buyer share token lifetime (days)', hint: 'Share links expire after this window; fresh links can be issued any time (1–730).', min: 1, max: 730 },
        { key: 'file_retention_days', label: 'Generated file retention (days)', hint: '0 keeps documents forever; otherwise stale documents are swept (0–3650).', min: 0, max: 3650 },
];

export function SettingsView(_props: ViewProps): ReactElement {
        const resource = useResource<SettingsResponse>('settings');
        const [draft, setDraft] = useState<Settings | null>(null);
        const [busy, setBusy] = useState(false);

        useEffect(() => {
                if (resource.data && draft === null) {
                        setDraft(resource.data.settings);
                }
        }, [resource.data, draft]);

        const set = (key: keyof Settings, value: string | number | boolean): void => {
                setDraft((prev) => (prev === null ? prev : { ...prev, [key]: value }));
        };

        const save = (): void => {
                if (draft === null) {
                        return;
                }
                confirm({
                        title: 'Save settings?',
                        message: draft.delete_data_on_uninstall
                                ? 'WARNING: “Delete all data on uninstall” is enabled. If the plugin is ever uninstalled while this is on, ALL TradeSheet Studio data — packets, versions, quotes and order links history — is permanently deleted. Save anyway?'
                                : 'The settings below are written to the store and apply to newly published packets and quotes.',
                        confirmLabel: 'Save settings',
                        danger: draft.delete_data_on_uninstall,
                }).then((proceed) => {
                        if (!proceed || draft === null) {
                                return;
                        }
                        setBusy(true);
                        apiPost<{ settings: Settings }>('settings', draft)
                                .then((res) => {
                                        setDraft(res.settings);
                                        toast.success('Settings saved.');
                                })
                                .catch((err: unknown) => toast.error(errorMessage(err)))
                                .finally(() => setBusy(false));
                });
        };

        if (resource.loading && !resource.data) {
                return (
                        <div className="tss-view">
                                <PageHeader title="Settings" />
                                <Card><Skeleton rows={8} /></Card>
                        </div>
                );
        }

        if (resource.error || !resource.data || draft === null) {
                return (
                        <div className="tss-view">
                                <PageHeader title="Settings" />
                                <ErrorBanner message={resource.error ?? 'Settings could not be loaded.'} onRetry={resource.reload} />
                        </div>
                );
        }

        return (
                <div className="tss-view">
                        <PageHeader
                                title="Settings"
                                subtitle="Company presentation, document defaults and lifecycle windows"
                                actions={
                                        <span className="tss-pagehead__actions">
                                                <Button variant="primary" busy={busy} onClick={save}>Save settings</Button>
                                                <Button variant="ghost" busy={busy} onClick={() => setDraft(resource.data?.settings ?? null)}>Reset</Button>
                                        </span>
                                }
                        />

                        <ErrorBanner message={resource.error} onRetry={resource.reload} />

                        <p className="tss-note">
                                Store currency: <strong>{resource.data.currency || '—'} {resource.data.currency_symbol}</strong> — packets are issued
                                in the store currency (multi-currency is not supported in version 1).
                        </p>

                        <Card title="Company presentation" subtitle="Printed on offer documents; empty fields fall back to the WordPress/WooCommerce store identity">
                                <div className="tss-form-grid">
                                        <Field label="Company name">
                                                <TextInput value={draft.company_name} onChange={(v) => set('company_name', v)} placeholder="e.g. Kaur Industrial Supplies" />
                                        </Field>
                                        <Field label="Company email">
                                                <TextInput type="email" value={draft.company_email} onChange={(v) => set('company_email', v)} placeholder="sales@example.com" />
                                        </Field>
                                        <Field label="Company phone">
                                                <TextInput value={draft.company_phone} onChange={(v) => set('company_phone', v)} placeholder="+91 20 5555 0100" />
                                        </Field>
                                        <Field label="Logo attachment id" hint="WordPress media library ID of the logo (numeric).">
                                                <TextInput type="number" value={String(draft.logo_id)} onChange={(v) => set('logo_id', Math.max(0, Number(v) || 0))} min={0} mono />
                                        </Field>
                                </div>
                                <Field label="Company address">
                                        <TextArea value={draft.company_address} onChange={(v) => set('company_address', v)} rows={2} placeholder="Street, city, state, postal code, country" />
                                </Field>
                                <Field label="Document footer" hint="Printed at the bottom of every generated document.">
                                        <TextArea value={draft.doc_footer} onChange={(v) => set('doc_footer', v)} rows={2} placeholder="e.g. Prices exclusive of taxes and freight. Terms subject to our standard conditions." />
                                </Field>
                        </Card>

                        <Card title="Documents & defaults">
                                <div className="tss-form-grid">
                                        <Field label="Document size" hint="Paper format for PDF documents.">
                                                <Select value={draft.doc_size} onChange={(v) => set('doc_size', v)}>
                                                        {DOC_SIZES.map((size) => (
                                                                <option key={size.id} value={size.id}>{size.label}</option>
                                                        ))}
                                                </Select>
                                        </Field>
                                        <Field label="Default template" hint="Pre-selected in the composer's presentation step.">
                                                <Select value={draft.default_template} onChange={(v) => set('default_template', v)}>
                                                        {TEMPLATES.map((template) => (
                                                                <option key={template.id} value={template.id}>{template.label}</option>
                                                        ))}
                                                </Select>
                                        </Field>
                                </div>
                                <div className="tss-form-grid">
                                        {NUM_FIELDS.map((field) => (
                                                <Field key={field.key} label={field.label} hint={field.hint}>
                                                        <TextInput
                                                                type="number"
                                                                value={String(draft[field.key])}
                                                                onChange={(v) => set(field.key, Math.max(field.min, Math.min(field.max, Number(v) || 0)))}
                                                                min={field.min}
                                                                max={field.max}
                                                        />
                                                </Field>
                                        ))}
                                </div>
                        </Card>

                        <Card title="Notifications & data">
                                <Toggle
                                        checked={draft.notify_emails}
                                        onChange={(v) => set('notify_emails', v)}
                                        label="Email notifications"
                                        hint="Send the merchant an email when a quote request arrives, and the buyer notifications on revisions and orders."
                                />
                                <div className="tss-dangerzone">
                                        <Toggle
                                                checked={draft.delete_data_on_uninstall}
                                                onChange={(v) => set('delete_data_on_uninstall', v)}
                                                label="Delete ALL data on uninstall"
                                                hint="When the plugin is uninstalled with this enabled, every packet, version, quote, document and order link is permanently deleted — including your orders history. Leave this OFF unless you truly intend a full wipe."
                                                danger
                                        />
                                        <p className="tss-dangerzone__warn" role="alert">
                                                <strong>Danger:</strong> enabling this means uninstalling the plugin erases the entire TradeSheet Studio
                                                history — issued offers, immutable versions, quotes and WooCommerce order links. This cannot be undone.
                                        </p>
                                </div>
                        </Card>

                        <div className="tss-wizard__actions">
                                <Button variant="primary" busy={busy} onClick={save}>Save settings</Button>
                        </div>
                </div>
        );
}
