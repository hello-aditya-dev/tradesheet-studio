/**
 * Entry point: mounts the merchant admin app into #tss-admin-root (rendered by
 * TradeSheetStudio\Admin\AdminPage). React comes from window.wp.element — see
 * wp.ts and the esbuild JSX factory.
 */
import { App } from './App';
import { mount } from './wp';

function boot(): void {
        const root = document.getElementById('tss-admin-root');
        if (!root) {
                return;
        }
        try {
                mount(<App />, root);
        } catch (err) {
                root.innerHTML = '';
                const notice = document.createElement('div');
                notice.className = 'notice notice-error';
                notice.innerHTML = '<p><strong>TradeSheet Studio:</strong> ' +
                        String(err instanceof Error ? err.message : String(err)).replace(/[<>&]/g, (c) => {
                                return c === '<' ? '&lt;' : c === '>' ? '&gt;' : '&amp;';
                        }) + '</p>';
                root.appendChild(notice);
        }
}

if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
} else {
        boot();
}
