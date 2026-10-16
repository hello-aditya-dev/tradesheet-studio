# TradeSheet Studio — Marketing Site

The product landing page for TradeSheet Studio, published at:

**https://hello-aditya-dev.github.io/tradesheet-studio/**

It is a Next.js 16 (App Router) single-page site that exports to a **fully
static bundle** — no API routes, no server actions, no database — which is
what makes GitHub Pages hosting possible.

## Structure

```
site/
├── public/                 # logo.svg, robots.txt
├── src/
│   ├── app/                # layout.tsx, page.tsx (the single route), globals.css
│   ├── components/site/    # 21 section components + interactive demo/
│   ├── components/ui/      # shadcn/ui subset used by the site
│   ├── hooks/use-toast.ts
│   └── lib/utils.ts
├── next.config.ts          # output: "export" + basePath /tradesheet-studio
├── package.json
├── postcss.config.mjs      # Tailwind CSS 4
└── tsconfig.json
```

## Commands

```bash
bun install             # install dependencies
bun run dev             # local dev (note: served under /tradesheet-studio)
bun run build:static    # static export → ./out
bun run typecheck       # tsc --noEmit
```

## Deployment

Pushes to `main` that touch `site/**` (or the workflow file itself) trigger
`.github/workflows/deploy-site.yml`, which builds the static export with Bun
and publishes it to GitHub Pages via `actions/deploy-pages`.

The `basePath` is `/tradesheet-studio` (GitHub Pages project-site convention).
When moving to a custom domain, set `PAGES_BASE_PATH=""` for the build and
commit a `CNAME` file in `site/public/`.

## Editing the site

The canonical editing environment lives outside this repository; when the
section components change there, re-sync `src/` into this folder (the sources
are kept byte-identical apart from `next.config.ts`).
