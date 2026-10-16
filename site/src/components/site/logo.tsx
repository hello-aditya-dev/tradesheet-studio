import * as React from "react"

import { cn } from "@/lib/utils"

/**
 * Original TradeSheet Studio mark: a layered document/sheet stack where the
 * front sheet (a price table) sends out an amber arrow — the catalog
 * becoming an order. Simple geometry, teal + amber.
 */
export function LogoMark({ className, ...props }: React.ComponentProps<"svg">) {
  return (
    <svg
      viewBox="0 0 32 32"
      role="img"
      aria-label="TradeSheet Studio logo"
      className={cn("size-8", className)}
      {...props}
    >
      <rect x="1" y="1" width="30" height="30" rx="8" fill="#0f766e" />
      {/* back sheet — the stack */}
      <rect
        x="12"
        y="5.5"
        width="13"
        height="15"
        rx="2.5"
        fill="none"
        stroke="#99f6e4"
        strokeWidth="1.6"
        opacity="0.8"
      />
      {/* front sheet — the price table */}
      <rect x="6.5" y="8.5" width="13" height="15" rx="2.5" fill="#ffffff" />
      {/* table rows */}
      <rect x="9.2" y="12" width="7.6" height="1.7" rx="0.85" fill="#0f766e" />
      <rect x="9.2" y="15.5" width="7.6" height="1.2" rx="0.6" fill="#14b8a6" opacity="0.6" />
      <rect x="9.2" y="18.3" width="4.8" height="1.2" rx="0.6" fill="#14b8a6" opacity="0.6" />
      {/* amber arrow — the sheet becomes an order */}
      <path
        d="M13.5 27.2 H 23.6"
        stroke="#fbbf24"
        strokeWidth="2.4"
        strokeLinecap="round"
      />
      <path
        d="M20.4 23.8 L 24.4 27.2 L 20.4 30.6"
        fill="none"
        stroke="#fbbf24"
        strokeWidth="2.4"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  )
}

export function LogoWordmark({
  className,
  markClassName,
}: {
  className?: string
  markClassName?: string
}) {
  return (
    <span className={cn("inline-flex items-center gap-2.5", className)}>
      <LogoMark className={cn("size-8 shrink-0", markClassName)} />
      <span className="text-lg font-semibold tracking-tight text-stone-900">
        TradeSheet<span className="text-teal-700"> Studio</span>
      </span>
    </span>
  )
}
