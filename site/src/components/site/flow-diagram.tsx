import * as React from "react"
import { ChevronDown } from "lucide-react"

import { cn } from "@/lib/utils"

/**
 * Original flow diagram: WooCommerce products → price book → composer →
 * immutable snapshot (amber) → three anchored representations → quote
 * integrity engine → merchant approval → WooCommerce order (amber).
 *
 * Desktop renders as a single SVG; mobile reflows into a vertical CSS flow.
 */
export function FlowDiagram({ className }: { className?: string }) {
  return (
    <div className={cn("w-full", className)}>
      {/* Desktop / tablet: SVG diagram */}
      <div className="hidden md:block">
        <svg
          viewBox="0 0 1620 320"
          role="img"
          aria-labelledby="flow-title flow-desc"
          className="h-auto w-full"
        >
          <title id="flow-title">DealPacket integrity flow</title>
          <desc id="flow-desc">
            WooCommerce products feed a wholesale price book, then the packet
            composer. Publishing freezes an immutable snapshot with a SHA-256
            checksum. The PDF catalog, CSV order sheet and buyer portal all
            anchor to that snapshot. A quote integrity engine checks requested
            quotes against it, the merchant approves, and the final WooCommerce
            order is created at quoted prices.
          </desc>
          <defs>
            <marker
              id="ts-arrow"
              viewBox="0 0 10 10"
              refX="9"
              refY="5"
              markerWidth="7"
              markerHeight="7"
              orient="auto-start-reverse"
            >
              <path d="M0 0 L10 5 L0 10 z" fill="#a8a29e" />
            </marker>
          </defs>

          {/* WooCommerce products */}
          <rect x="8" y="125" width="142" height="50" rx="10" fill="#f0fdfa" stroke="#0f766e" strokeWidth="1.5" />
          <text x="79" y="143" textAnchor="middle" fontSize="13" fontWeight="600" fill="#134e4a">WooCommerce</text>
          <text x="79" y="161" textAnchor="middle" fontSize="13" fontWeight="600" fill="#134e4a">products</text>

          <line x1="150" y1="150" x2="183" y2="150" stroke="#a8a29e" strokeWidth="2" markerEnd="url(#ts-arrow)" />

          {/* Price book */}
          <rect x="185" y="125" width="140" height="50" rx="10" fill="#f0fdfa" stroke="#0f766e" strokeWidth="1.5" />
          <text x="255" y="143" textAnchor="middle" fontSize="13" fontWeight="600" fill="#134e4a">Wholesale</text>
          <text x="255" y="161" textAnchor="middle" fontSize="13" fontWeight="600" fill="#134e4a">price book</text>

          <line x1="325" y1="150" x2="360" y2="150" stroke="#a8a29e" strokeWidth="2" markerEnd="url(#ts-arrow)" />

          {/* Packet composer */}
          <rect x="362" y="125" width="140" height="50" rx="10" fill="#f0fdfa" stroke="#0f766e" strokeWidth="1.5" />
          <text x="432" y="143" textAnchor="middle" fontSize="13" fontWeight="600" fill="#134e4a">Packet</text>
          <text x="432" y="161" textAnchor="middle" fontSize="13" fontWeight="600" fill="#134e4a">composer</text>

          <line x1="502" y1="150" x2="538" y2="150" stroke="#a8a29e" strokeWidth="2" markerEnd="url(#ts-arrow)" />

          {/* Immutable snapshot (amber) */}
          <rect x="540" y="110" width="190" height="80" rx="12" fill="#fffbeb" stroke="#d97706" strokeWidth="2.5" />
          <text x="635" y="146" textAnchor="middle" fontSize="14" fontWeight="700" fill="#78350f">Immutable snapshot</text>
          <text x="635" y="167" textAnchor="middle" fontSize="11" fill="#b45309">sha256 · v1 · 30-day validity</text>

          {/* Fan-out to the three representations */}
          <line x1="730" y1="140" x2="803" y2="46" stroke="#a8a29e" strokeWidth="2" markerEnd="url(#ts-arrow)" />
          <line x1="730" y1="150" x2="803" y2="158" stroke="#a8a29e" strokeWidth="2" markerEnd="url(#ts-arrow)" />
          <line x1="730" y1="160" x2="803" y2="272" stroke="#a8a29e" strokeWidth="2" markerEnd="url(#ts-arrow)" />

          {/* Branch nodes */}
          <rect x="810" y="18" width="170" height="44" rx="10" fill="#f0fdfa" stroke="#0f766e" strokeWidth="1.5" />
          <text x="895" y="45" textAnchor="middle" fontSize="13" fontWeight="600" fill="#134e4a">PDF catalog</text>

          <rect x="810" y="138" width="170" height="44" rx="10" fill="#f0fdfa" stroke="#0f766e" strokeWidth="1.5" />
          <text x="895" y="165" textAnchor="middle" fontSize="13" fontWeight="600" fill="#134e4a">CSV order sheet</text>

          <rect x="810" y="258" width="170" height="44" rx="10" fill="#f0fdfa" stroke="#0f766e" strokeWidth="1.5" />
          <text x="895" y="285" textAnchor="middle" fontSize="13" fontWeight="600" fill="#134e4a">Buyer portal</text>

          {/* Converge into integrity engine */}
          <line x1="980" y1="40" x2="1056" y2="142" stroke="#a8a29e" strokeWidth="2" markerEnd="url(#ts-arrow)" />
          <line x1="980" y1="160" x2="1056" y2="150" stroke="#a8a29e" strokeWidth="2" markerEnd="url(#ts-arrow)" />
          <line x1="980" y1="280" x2="1056" y2="158" stroke="#a8a29e" strokeWidth="2" markerEnd="url(#ts-arrow)" />

          {/* Quote integrity engine */}
          <rect x="1058" y="125" width="150" height="50" rx="10" fill="#f0fdfa" stroke="#0f766e" strokeWidth="1.5" />
          <text x="1133" y="143" textAnchor="middle" fontSize="13" fontWeight="600" fill="#134e4a">Quote integrity</text>
          <text x="1133" y="161" textAnchor="middle" fontSize="13" fontWeight="600" fill="#134e4a">engine</text>

          <line x1="1208" y1="150" x2="1243" y2="150" stroke="#a8a29e" strokeWidth="2" markerEnd="url(#ts-arrow)" />

          {/* Merchant approval */}
          <rect x="1245" y="125" width="135" height="50" rx="10" fill="#f0fdfa" stroke="#0f766e" strokeWidth="1.5" />
          <text x="1312" y="143" textAnchor="middle" fontSize="13" fontWeight="600" fill="#134e4a">Merchant</text>
          <text x="1312" y="161" textAnchor="middle" fontSize="13" fontWeight="600" fill="#134e4a">approval</text>

          <line x1="1380" y1="150" x2="1416" y2="150" stroke="#a8a29e" strokeWidth="2" markerEnd="url(#ts-arrow)" />

          {/* WooCommerce order (amber) */}
          <rect x="1418" y="110" width="194" height="80" rx="12" fill="#fffbeb" stroke="#d97706" strokeWidth="2.5" />
          <text x="1515" y="142" textAnchor="middle" fontSize="13" fontWeight="600" fill="#78350f">WooCommerce</text>
          <text x="1515" y="161" textAnchor="middle" fontSize="15" fontWeight="700" fill="#78350f">ORDER</text>
          <text x="1515" y="179" textAnchor="middle" fontSize="11" fill="#b45309">at quoted prices</text>
        </svg>
      </div>

      {/* Mobile: vertical CSS flow */}
      <ol className="flex flex-col items-stretch gap-2 md:hidden" aria-label="DealPacket integrity flow, vertical">
        <FlowNode label="WooCommerce products" />
        <FlowArrow />
        <FlowNode label="Wholesale price book" />
        <FlowArrow />
        <FlowNode label="Packet composer" />
        <FlowArrow />
        <FlowNode label="Immutable snapshot · sha256 · v1 · 30-day validity" highlight />
        <FlowArrow />
        <li className="rounded-xl border border-teal-200 bg-teal-50/60 p-3">
          <p className="mb-2 text-center text-[11px] font-semibold tracking-wide text-teal-800 uppercase">
            One snapshot · three representations
          </p>
          <div className="flex flex-col gap-2">
            <NodeBox label="PDF catalog" />
            <NodeBox label="CSV order sheet" />
            <NodeBox label="Buyer portal" />
          </div>
        </li>
        <FlowArrow />
        <FlowNode label="Quote integrity engine" />
        <FlowArrow />
        <FlowNode label="Merchant approval" />
        <FlowArrow />
        <FlowNode label="WooCommerce order · at quoted prices" highlight />
      </ol>
    </div>
  )
}

function NodeBox({ label, highlight }: { label: string; highlight?: boolean }) {
  return (
    <div
      className={cn(
        "rounded-lg border px-4 py-2.5 text-center text-sm font-semibold",
        highlight
          ? "border-amber-400 bg-amber-50 text-amber-900"
          : "border-teal-200 bg-teal-50 text-teal-900"
      )}
    >
      {label}
    </div>
  )
}

function FlowNode({ label, highlight }: { label: string; highlight?: boolean }) {
  return (
    <li>
      <NodeBox label={label} highlight={highlight} />
    </li>
  )
}

function FlowArrow() {
  return (
    <li aria-hidden="true" className="flex justify-center py-0.5 text-stone-400">
      <ChevronDown className="size-4" />
    </li>
  )
}
