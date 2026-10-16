/**
 * Ambient declarations for everything the admin bundle expects from the
 * WordPress host page:
 *
 *  - `window.wp` — the script globals `wp-element` / `wp-i18n` / `wp-components`
 *    provide the React runtime. NOTHING React-related is bundled; the JSX
 *    factory is `wp.element.createElement` (see esbuild.mjs), so the emitted
 *    code references the global directly.
 *  - `window.TSS_ADMIN` — bootstrap object injected by AdminPage.php.
 *  - a classic (non-automatic) JSX namespace so `tsc --noEmit` can check the
 *    .tsx files with `"jsx": "preserve"` while esbuild performs the transform.
 *
 * The React-flavored types below are hand-written for the small API subset the
 * app uses (hooks + createElement + event handlers). They are NOT @types/react
 * — there is no React package in package.json at all.
 */

/* --------------------------------------------------------- core node types */

export type Key = string | number;

export interface ReactElement {
        type: unknown;
        props: Record<string, unknown> | null;
        key: Key | null;
}

export type ReactNode = ReactElement | string | number | boolean | null | undefined | readonly ReactNode[];

/* ------------------------------------------------------------ state types */

export type SetStateAction<S> = S | ((previous: S) => S);
export type Dispatch<A> = (action: A) => void;

export interface MutableRefObject<T> {
        current: T;
}

/* --------------------------------------------------------- handler events */

export interface BaseSyntheticEvent<TTarget> {
        target: TTarget;
        currentTarget: TTarget;
        stopPropagation(): void;
        preventDefault(): void;
}

export interface MouseEvent<TTarget = Element> extends BaseSyntheticEvent<TTarget> {}
export interface KeyboardEvent<TTarget = Element> extends BaseSyntheticEvent<TTarget> {
        key: string;
}
export interface ChangeEvent<TTarget = Element> extends BaseSyntheticEvent<TTarget> {}
export interface FocusEvent<TTarget = Element> extends BaseSyntheticEvent<TTarget> {}

/* --------------------------------------------------- the wp.element global */

export interface WpElementModule {
        createElement(
                type: unknown,
                props?: Record<string, unknown> | null,
                ...children: ReactNode[]
        ): ReactElement;

        readonly Fragment: unknown;
        readonly StrictMode: unknown;

        useState: {
                <S>(initialState: S | (() => S)): [S, Dispatch<SetStateAction<S>>];
                <S = undefined>(): [S | undefined, Dispatch<SetStateAction<S | undefined>>];
        };

        useEffect(effect: () => void | (() => void), deps?: readonly unknown[]): void;

        useMemo<T>(factory: () => T, deps: readonly unknown[] | undefined): T;

        useRef<T>(initial: T): MutableRefObject<T>;

        useCallback<T>(callback: T, deps: readonly unknown[] | undefined): T;

        useContext<T>(context: WpContext<T>): T;

        createContext<T>(defaultValue: T): WpContext<T>;

        useReducer<S, A>(reducer: (state: S, action: A) => S, initialState: S): [S, Dispatch<A>];

        /** Legacy ReactDOM.render shim exposed by @wordpress/element. */
        render(element: ReactNode, container: Element | DocumentFragment): unknown;

        /** Modern React 18 root API (present in current @wordpress/element). */
        createRoot?(container: Element | DocumentFragment): { render(element: ReactNode): unknown };
}

export interface WpContext<T> {
        readonly Provider: unknown;
        readonly Consumer: unknown;
        __tssContextValue?: T;
}

/* ------------------------------------------------------ the wp.i18n global */

export interface WpI18nModule {
        __(text: string, domain?: string): string;
        _x(text: string, context: string, domain?: string): string;
        sprintf(format: string, ...args: unknown[]): string;
}

/* ------------------------------------------------- the TSS_ADMIN bootstrap */

export interface TssAdminConfig {
        restUrl: string;
        nonce: string;
        version: string;
        currency: string;
        currencySymbol: string;
        adminUrl: string;
        homeUrl: string;
        pdfRenderer: boolean;
}

declare global {
        const wp: {
                element: WpElementModule;
                i18n?: WpI18nModule;
                /** Present but unused: the app ships its own scoped component kit. */
                components?: unknown;
                apiFetch?: unknown;
        };

        interface Window {
                wp: typeof wp;
                /** Bootstrap injected by TradeSheetStudio\Admin\AdminPage. */
                TSS_ADMIN: TssAdminConfig;
        }

        /** Classic JSX namespace used by tsc while esbuild does the transform. */
        namespace JSX {
                type Element = ReactElement;
                interface ElementAttributesProperty {
                        props: Record<string, unknown>;
                }
                interface ElementChildrenAttribute {
                        children: Record<string, unknown>;
                }
                interface IntrinsicAttributes {
                        key?: Key;
                }
                /**
                 * DOM attribute checking is intentionally loose: DOM interaction is
                 * funneled through the typed components in components/ui.tsx.
                 */
                interface IntrinsicElements {
                        [name: string]: Record<string, unknown>;
                }
        }
}

export {};
