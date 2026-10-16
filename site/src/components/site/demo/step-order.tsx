"use client"

import * as React from "react"
import { Lock, Repeat2, ShoppingCart } from "lucide-react"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card } from "@/components/ui/card"
import { Separator } from "@/components/ui/separator"
import {
  PACKET_ID,
  PACKET_VERSION,
  formatINR,
  resolveUnitPrice,
  type DemoProduct,
} from "@/components/site/demo/demo-data"

const RAISED_PRICE = 4984

export function StepOrder({
  selectedProducts,
  quoteQty,
  simulate,
  decision,
  onAcceptAgain,
  onRestart,
}: {
  selectedProducts: DemoProduct[]
  quoteQty: Record<string, number> | null
  simulate: boolean
  decision: "honor" | "propose-new" | null
  onAcceptAgain: () => void
  onRestart: () => void
}) {
  const lines = selectedProducts
    .map((product) => {
      const qty = quoteQty?.[product.sku] ?? 0
      let unitPrice = resolveUnitPrice(product, qty)
      if (simulate && decision === "propose-new" && product.sku === "VW-LED-HB150") {
        unitPrice = RAISED_PRICE
      }
      return { product, qty, unitPrice, total: qty * unitPrice }
    })
    .filter((line) => line.qty > 0)

  const orderTotal = lines.reduce((sum, line) => sum + line.total, 0)
  const revisionLabel = decision ? "R2" : null

  return (
    <div className="flex flex-col gap-4">
      <div>
        <h3 className="text-base font-semibold tracking-tight text-stone-900">
          Step 5 — Accepted → order
        </h3>
        <p className="mt-1 text-sm text-stone-600">
          The buyer accepts revision {revisionLabel ?? "R1"} in the portal, and
          the idempotent order bridge converts the accepted quote into a
          WooCommerce order — once.
        </p>
      </div>

      <Card className="gap-0 overflow-hidden border-stone-300 py-0 shadow-md shadow-stone-200/60">
        {/* order header, styled like a WooCommerce order */}
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-stone-200 bg-stone-800 px-5 py-3.5 text-white">
          <p className="flex items-center gap-2 text-sm font-semibold">
            <ShoppingCart className="size-4 text-amber-400" aria-hidden="true" />
            Order #1043
          </p>
          <div className="flex flex-wrap items-center gap-2">
            <Badge className="bg-teal-600 text-white">Processing</Badge>
            <span className="font-mono text-xs text-stone-300">
              created from {PACKET_ID} · {PACKET_VERSION}
              {revisionLabel ? ` · revision ${revisionLabel}` : ""}
            </span>
          </div>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead>
              <tr className="border-b border-stone-200 bg-stone-50 text-xs tracking-wide text-stone-500 uppercase">
                <th className="px-5 py-2.5 font-medium">Item</th>
                <th className="py-2.5 font-medium">Qty</th>
                <th className="py-2.5 font-medium">Unit price</th>
                <th className="px-5 py-2.5 text-right font-medium">Total</th>
              </tr>
            </thead>
            <tbody>
              {lines.map((line) => (
                <tr key={line.product.sku} className="border-b border-stone-100 last:border-b-0">
                  <td className="max-w-[18rem] px-5 py-2.5">
                    <p className="font-mono text-xs text-stone-500">
                      {line.product.sku}
                    </p>
                    <p className="truncate text-stone-800">{line.product.name}</p>
                    {simulate && line.product.sku === "VW-LED-HB150" && (
                      <p className="mt-0.5 text-xs text-stone-500">
                        {decision === "honor"
                          ? `at the quoted ${formatINR(line.unitPrice)} — not the new ${formatINR(RAISED_PRICE)}`
                          : `at the R2 revised ${formatINR(RAISED_PRICE)} — buyer accepted explicitly`}
                      </p>
                    )}
                  </td>
                  <td className="py-2.5 font-mono text-stone-700">{line.qty}</td>
                  <td className="py-2.5 font-mono text-stone-700">
                    {formatINR(line.unitPrice)}
                  </td>
                  <td className="px-5 py-2.5 text-right font-mono font-medium text-teal-800">
                    {formatINR(line.total)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <div className="flex items-center justify-between border-t border-stone-200 bg-stone-50 px-5 py-3.5">
          <p className="text-xs text-stone-500">
            Line items at quoted prices — taxes &amp; shipping resolved by
            WooCommerce at checkout
          </p>
          <p className="font-mono text-lg font-semibold text-stone-900">
            {formatINR(orderTotal)}
          </p>
        </div>
      </Card>

      <div className="flex flex-wrap items-center gap-2">
        <Badge
          variant="outline"
          className="border-stone-300 bg-white px-3 py-1 font-mono text-xs text-stone-600"
        >
          <Lock aria-hidden="true" />
          idempotency key: {PACKET_ID}/{PACKET_VERSION}/{revisionLabel ?? "R1"} — repeat accepts are refused
        </Badge>
      </div>

      <Separator className="bg-stone-200" />

      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p className="max-w-xl text-xs leading-relaxed text-stone-500">
          Payment runs through the merchant&apos;s normal WooCommerce checkout —
          the plugin never charges anyone. Try accepting again: the bridge
          refuses to create a second order from the same accepted revision.
        </p>
        <div className="flex flex-wrap gap-2">
          <Button
            variant="outline"
            onClick={onRestart}
            className="h-10 border-stone-300 hover:bg-stone-100 hover:text-stone-900"
          >
            Restart demo
          </Button>
          <Button
            onClick={onAcceptAgain}
            className="h-10 bg-amber-500 px-5 text-amber-950 hover:bg-amber-600"
          >
            <Repeat2 aria-hidden="true" />
            Accept again
          </Button>
        </div>
      </div>
    </div>
  )
}
