/**
 * Typed access layer for the WordPress globals. React itself MUST come from
 * `window.wp.element` (provided by the `wp-element` script handle); nothing
 * here imports React at runtime — there is no React dependency at all.
 *
 * JSX files compile with the factory `wp.element.createElement` (esbuild), so
 * every element in the bundle flows through the global validated here.
 */
import type { ReactNode, WpElementModule } from './types/wp';

const elementModule = (): WpElementModule => {
        const globalWp = typeof window !== 'undefined' ? window.wp : undefined;
        if (!globalWp || !globalWp.element) {
                throw new Error('TradeSheet Studio requires the wp.element global (WordPress 6.4+ with wp-element enqueued).');
        }
        return globalWp.element as WpElementModule;
};

export const wpElement = elementModule();

export const createElement = wpElement.createElement;
export const Fragment = wpElement.Fragment;
export const StrictMode = wpElement.StrictMode;

export const useState = wpElement.useState;
export const useEffect = wpElement.useEffect;
export const useMemo = wpElement.useMemo;
export const useRef = wpElement.useRef;
export const useCallback = wpElement.useCallback;
export const useContext = wpElement.useContext;
export const createContext = wpElement.createContext;
export const useReducer = wpElement.useReducer;

/** Mount the app, preferring the modern createRoot API when available. */
export function mount(node: ReactNode, container: Element): void {
        const api = elementModule();
        if (typeof api.createRoot === 'function') {
                api.createRoot(container).render(node);
        } else if (typeof api.render === 'function') {
                api.render(node, container);
        } else {
                throw new Error('wp.element provides neither createRoot nor render.');
        }
}

/** Safely translate a string when wp.i18n is present (English fallback). */
export function translate(text: string): string {
        const i18n = typeof window !== 'undefined' ? window.wp?.i18n : undefined;
        if (i18n && typeof i18n.__ === 'function') {
                try {
                        return i18n.__(text, 'tradesheet-studio');
                } catch {
                        return text;
                }
        }
        return text;
}
