/**
 * REST client for the plugin's `tradesheet-studio/v1` namespace.
 *
 * Conventions (from src/Api/Routes.php + controllers):
 * - cookie auth + `X-WP-Nonce` header on EVERY request (including GET);
 * - JSON bodies; errors surface as `{ code, message, data: { status } }`;
 * - money values are decimal strings and are echoed back as strings.
 */
import type { TssAdminConfig } from './types/wp';

export class ApiError extends Error {
	readonly code: string;
	readonly status: number;

	constructor(message: string, code: string, status: number) {
		super(message);
		this.name = 'ApiError';
		this.code = code;
		this.status = status;
	}
}

export function bootstrap(): TssAdminConfig {
	if (typeof window === 'undefined' || !window.TSS_ADMIN) {
		throw new ApiError('TradeSheet Studio bootstrap data (window.TSS_ADMIN) is missing.', 'tss_bootstrap', 0);
	}
	return window.TSS_ADMIN;
}

export function currencySymbol(): string {
	try {
		return bootstrap().currencySymbol || '';
	} catch {
		return '';
	}
}

export function apiUrl(path: string): string {
	const base = bootstrap().restUrl;
	const suffix = path.replace(/^\/+/, '');
	return base.endsWith('/') ? base + suffix : `${base}/${suffix}`;
}

async function toApiError(res: Response): Promise<ApiError> {
	let message = `Request failed with HTTP ${res.status}.`;
	let code = `tss_http_${res.status}`;
	try {
		const data: unknown = await res.json();
		if (data && typeof data === 'object') {
			const obj = data as { message?: unknown; code?: unknown };
			if (typeof obj.message === 'string' && obj.message !== '') {
				message = obj.message;
			}
			if (typeof obj.code === 'string' && obj.code !== '') {
				code = obj.code;
			}
		}
	} catch {
		// Non-JSON error body — keep the generic message.
	}
	return new ApiError(message, code, res.status);
}

async function request<T>(path: string, method: string, body?: unknown): Promise<T> {
	const cfg = bootstrap();
	const headers: Record<string, string> = { 'X-WP-Nonce': cfg.nonce };
	let payload: string | undefined;
	if (body !== undefined) {
		headers['Content-Type'] = 'application/json';
		payload = JSON.stringify(body);
	}

	const res = await fetch(apiUrl(path), {
		method,
		credentials: 'same-origin',
		headers,
		body: payload,
	});

	if (!res.ok) {
		throw await toApiError(res);
	}

	if (res.status === 204) {
		return undefined as T;
	}

	return (await res.json()) as T;
}

export function apiGet<T>(path: string): Promise<T> {
	return request<T>(path, 'GET');
}

export function apiPost<T>(path: string, body?: unknown): Promise<T> {
	return request<T>(path, 'POST', body === undefined ? {} : body);
}

export function apiDelete<T>(path: string, body?: unknown): Promise<T> {
	return request<T>(path, 'DELETE', body);
}

/**
 * Authenticated download. The REST download routes require the nonce header,
 * so plain links cannot be used; fetch the bytes and hand them to the browser
 * through an object URL instead.
 */
export async function apiDownload(path: string, filename: string): Promise<void> {
	const cfg = bootstrap();
	const res = await fetch(apiUrl(path), {
		method: 'GET',
		credentials: 'same-origin',
		headers: { 'X-WP-Nonce': cfg.nonce },
	});

	if (!res.ok) {
		throw await toApiError(res);
	}

	const blob = await res.blob();
	const url = URL.createObjectURL(blob);
	const anchor = document.createElement('a');
	anchor.href = url;
	anchor.download = filename;
	document.body.appendChild(anchor);
	anchor.click();
	anchor.remove();
	window.setTimeout(() => URL.revokeObjectURL(url), 15000);
}

export function errorMessage(err: unknown): string {
	if (err instanceof Error) {
		return err.message;
	}
	return String(err);
}
