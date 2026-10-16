/**
 * TradeSheet Studio admin bundle.
 *
 * Constraints:
 * - React and all @wordpress/* packages are provided by WordPress as globals.
 *   This bundle must NEVER contain a React runtime, so nothing imports React
 *   at runtime — TypeScript sees only hand-written type declarations in
 *   src/types/wp.d.ts, and esbuild's JSX factory is `wp.element.createElement`
 *   (fragment: `wp.element.Fragment`), which emits direct references to the
 *   WordPress global at every JSX site.
 * - tsconfig uses `"jsx": "preserve"`; the explicit build options below take
 *   precedence and perform the classic createElement transform.
 */
import * as esbuild from 'esbuild';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';
import { readFile, stat } from 'node:fs/promises';

const here = dirname(fileURLToPath(import.meta.url));
const assetsDir = resolve(here, '../plugin/tradesheet-studio/assets');

const banner = '/* TradeSheet Studio admin — bundled; React from window.wp (wp.element). Source: frontend/src. */';

const shared = {
        bundle: true,
        format: 'iife',
        target: 'es2019',
        minify: true,
        sourcemap: false,
        logLevel: 'info',
        tsconfig: resolve(here, 'tsconfig.json'),
        banner: { js: banner, css: banner },
};

await esbuild.build({
        ...shared,
        entryPoints: [resolve(here, 'src/index.tsx')],
        outfile: resolve(assetsDir, 'admin.js'),
        jsx: 'transform',
        jsxFactory: 'wp.element.createElement',
        jsxFragment: 'wp.element.Fragment',
});

await esbuild.build({
        ...shared,
        entryPoints: [resolve(here, 'src/admin.css')],
        outfile: resolve(assetsDir, 'admin.css'),
        loader: { '.css': 'css' },
});

// Sanity checks: the bundle must reference the WordPress global rather than
// shipping a React runtime of its own, and both outputs must be non-empty.
const js = await readFile(resolve(assetsDir, 'admin.js'), 'utf8');
if (!/wp\s*\.\s*element/.test(js)) {
        throw new Error('[tss] built admin.js does not reference window.wp.element — aborting.');
}
if (js.includes('react.production.min.js') || /\/\*#\s*sourceMappingURL=react/.test(js)) {
        throw new Error('[tss] built admin.js appears to bundle a React runtime — aborting.');
}

const [jsStat, cssStat] = await Promise.all([
        stat(resolve(assetsDir, 'admin.js')),
        stat(resolve(assetsDir, 'admin.css')),
]);
console.log(`[tss] admin.js  → ${(jsStat.size / 1024).toFixed(1)} KB`);
console.log(`[tss] admin.css → ${(cssStat.size / 1024).toFixed(1)} KB`);
console.log('[tss] build complete →', assetsDir);
