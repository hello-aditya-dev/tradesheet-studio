import type { NextConfig } from "next";

// GitHub Pages project sites are served under /<repository-name>/.
// Set PAGES_BASE_PATH="" when serving from a custom domain (CNAME at root).
const basePath = process.env.PAGES_BASE_PATH ?? "/tradesheet-studio";

const nextConfig: NextConfig = {
  // Fully static export: `next build` emits plain HTML/CSS/JS to ./out —
  // exactly what GitHub Pages serves. The site has no API routes, no
  // server actions and no dynamic data, so nothing is lost by exporting.
  output: "export",
  basePath,
  images: { unoptimized: true },
  typescript: { ignoreBuildErrors: true },
  reactStrictMode: false,
};

export default nextConfig;
