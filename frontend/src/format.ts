/** Presentation helpers for dates, sizes and small text transforms. */

const EMPTY = '—';

export function formatDateTime(value: string | null | undefined): string {
	if (!value) {
		return EMPTY;
	}
	const v = value.trim();
	if (v.length >= 16) {
		return v.slice(0, 16).replace('T', ' ');
	}
	return v || EMPTY;
}

export function formatDate(value: string | null | undefined): string {
	if (!value) {
		return EMPTY;
	}
	const v = value.trim();
	return v.length >= 10 ? v.slice(0, 10) : v;
}

export function dateInputValue(value: string | null | undefined): string {
	if (!value) {
		return '';
	}
	return value.trim().slice(0, 10);
}

export function formatBytes(bytes: number): string {
	if (!Number.isFinite(bytes) || bytes <= 0) {
		return '0 B';
	}
	if (bytes < 1024) {
		return `${bytes} B`;
	}
	if (bytes < 1024 * 1024) {
		return `${(bytes / 1024).toFixed(1)} KB`;
	}
	return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
}

export function shortChecksum(value: string, keep = 12): string {
	if (!value) {
		return EMPTY;
	}
	return value.length <= keep ? value : `${value.slice(0, keep)}…`;
}

export function humanize(value: string | null | undefined): string {
	if (!value) {
		return EMPTY;
	}
	return value
		.replace(/_/g, ' ')
		.replace(/\b\w/g, (c) => c.toUpperCase())
		.trim();
}

export function isPresent(value: string | null | undefined): boolean {
	return value !== null && value !== undefined && String(value).trim() !== '';
}

/** Render qty breaks as "10+ @ 8.50 · 50+ @ 8.20". */
export function breaksLabel(breaks: readonly { qty: number; price: string }[] | null | undefined, symbol: string): string {
	if (!breaks || breaks.length === 0) {
		return EMPTY;
	}
	return breaks.map((b) => `${b.qty}+ @ ${moneyTrim(b.price)}`).join(' · ');

	function moneyTrim(p: string): string {
		const m = /^(\d+(?:[.]\d*)?)$/.exec(p.trim());
		let num = m ? m[1] : p;
		if (num.includes('.')) {
			num = num.replace(/0+$/, '');
			if (num.endsWith('.')) {
				num = num.slice(0, -1);
			}
		}
		return `${symbol}${num}`;
	}
}
