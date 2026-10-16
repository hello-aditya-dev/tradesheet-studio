import { useCallback, useEffect, useState } from './wp';
import { apiGet } from './api';

export interface Resource<T> {
	data: T | null;
	loading: boolean;
	loadingFresh: boolean;
	error: string | null;
	reload: () => void;
}

/**
 * Fetch a REST resource reactively. `path === null` keeps the resource idle.
 * The fetch is guarded against stale responses when the path changes.
 */
export function useResource<T>(path: string | null): Resource<T> {
	const [data, setData] = useState<T | null>(null);
	const [error, setError] = useState<string | null>(null);
	const [loading, setLoading] = useState<boolean>(path !== null);
	const [tick, setTick] = useState(0);

	useEffect(() => {
		if (path === null) {
			setData(null);
			setError(null);
			setLoading(false);
			return;
		}
		let alive = true;
		setLoading(true);
		setError(null);
		apiGet<T>(path)
			.then((result) => {
				if (alive) {
					setData(result);
					setLoading(false);
				}
			})
			.catch((err: unknown) => {
				if (alive) {
					setError(err instanceof Error ? err.message : String(err));
					setLoading(false);
				}
			});
		return () => {
			alive = false;
		};
	}, [path, tick]);

	const reload = useCallback(() => {
		setTick((t) => t + 1);
	}, []);

	return { data, loading, loadingFresh: loading && data !== null, error, reload };
}

export function useDebounced<T>(value: T, delay = 300): T {
	const [debounced, setDebounced] = useState(value);
	useEffect(() => {
		const id = window.setTimeout(() => setDebounced(value), delay);
		return () => window.clearTimeout(id);
	}, [value, delay]);
	return debounced;
}

/**
 * Async action wrapper with a busy flag, used for one-shot mutations.
 * Errors re-throw so callers can toast them consistently.
 */
export function useAction(): [boolean, (fn: () => Promise<void>) => Promise<void>] {
	const [busy, setBusy] = useState(false);
	const run = useCallback(async (fn: () => Promise<void>): Promise<void> => {
		if (busy) {
			return;
		}
		setBusy(true);
		try {
			await fn();
		} finally {
			setBusy(false);
		}
	}, [busy]);
	return [busy, run];
}

/** Toggle-style boolean state for panels and editors. */
export function useToggle(initial = false): [boolean, () => void, (v: boolean) => void] {
	const [open, setOpen] = useState(initial);
	const toggle = useCallback(() => setOpen((v) => !v), []);
	const set = useCallback((v: boolean) => setOpen(v), []);
	return [open, toggle, set];
}
