/**
 * Exact decimal-string arithmetic (BigInt, 4 decimal places) for money values
 * such as "4450.0000". Display-only computations never touch floats, and
 * values echoed back to the API stay strings end-to-end.
 */
const SCALE = 4;
const POW = BigInt(10000);
const MAX_QTY = 100000;

export function parseDecimal(value: string | number | null | undefined): bigint {
	if (value === null || value === undefined) {
		return BigInt(0);
	}
	const s = String(value).trim();
	if (s === '') {
		return BigInt(0);
	}
	const m = /^(-?)(\d*)(?:[.](\d*))?$/.exec(s);
	if (!m || (m[2] === '' && (m[3] === undefined || m[3] === ''))) {
		return BigInt(0);
	}
	const sign = m[1] === '-' ? BigInt(-1) : BigInt(1);
	const intPart = m[2] === '' ? BigInt(0) : BigInt(m[2]);
	const fracRaw = (m[3] ?? '').slice(0, SCALE).padEnd(SCALE, '0');
	const frac = fracRaw === '' ? BigInt(0) : BigInt(fracRaw);
	return sign * (intPart * POW + frac);
}

export function formatScaled(scaled: bigint): string {
	const negative = scaled < BigInt(0);
	const abs = negative ? -scaled : scaled;
	const intPart = abs / POW;
	const fracPart = (abs % POW).toString().padStart(SCALE, '0');
	return `${negative ? '-' : ''}${intPart.toString()}.${fracPart}`;
}

/** price × integer quantity, exact. */
export function mulQty(price: string | null | undefined, qty: number): string {
	const q = Math.max(0, Math.min(MAX_QTY, Math.floor(qty)));
	return formatScaled(parseDecimal(price) * BigInt(q));
}

export function addMoney(a: string | null | undefined, b: string | null | undefined): string {
	return formatScaled(parseDecimal(a) + parseDecimal(b));
}

function groupThousands(intStr: string): string {
	let out = '';
	for (let i = 0; i < intStr.length; i++) {
		const fromEnd = intStr.length - 1 - i;
		out = intStr[intStr.length - 1 - i] + out;
		if (fromEnd > 0 && fromEnd % 3 === 0) {
			out = ',' + out;
		}
	}
	return out;
}

/**
 * Format a decimal string for display: 2 decimals, widened to 4 when the
 * value carries sub-cent precision. Empty values render as an em dash.
 */
export function money(value: string | null | undefined, symbol: string): string {
	if (value === null || value === undefined || String(value).trim() === '') {
		return '—';
	}
	const negative = String(value).trim().startsWith('-');
	const abs = formatScaled(negative ? -parseDecimal(value) : parseDecimal(value));
	const [intPart, fracPart = ''] = abs.split('.');
	const hasSubCent = fracPart.length > 2 && fracPart.slice(2).replace(/0+$/, '') !== '';
	const frac = hasSubCent ? fracPart : fracPart.slice(0, 2);
	const num = `${groupThousands(intPart)}.${frac}`;
	return `${negative ? '-' : ''}${symbol}${num}`;
}

/** Line/total style estimate: Σ unit_price × qty. */
export function estimateTotal(lines: readonly { unit_price: string; qty: number }[]): string {
	let acc = BigInt(0);
	for (const line of lines) {
		acc += parseDecimal(mulQty(line.unit_price, line.qty));
	}
	return formatScaled(acc);
}
