"use client"

import * as React from "react"
import { AlertCircle, BadgeCheck, Send } from "lucide-react"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import {
  PACKET_ID,
  PACKET_VERSION,
  formatINR,
  isQtyValid,
  parseQty,
  qtyError,
  resolveUnitPrice,
  type DemoProduct,
} from "@/components/site/demo/demo-data"

export function StepQuote({
  selectedProducts,
  qtyInput,
  quoteQty,
  onQtyChange,
  onSubmit,
}: {
  selectedProducts: DemoProduct[]
  qtyInput: Record<string, string>
  quoteQty: Record<string, number> | null
  onQtyChange: (sku: string, value: string) => void
  onSubmit: () => void
}) {
  const submitted = quoteQty !== null

  const rows = selectedProducts.map((product) => {
    const qty = submitted ? (quoteQty?.[product.sku] ?? 0) : parseQty(qtyInput[product.sku])
    const valid = isQtyValid(product, qty)
    const unitPrice = Number.isNaN(qty) ? product.wholesalePrice : resolveUnitPrice(product, qty)
    const breakApplied =
      !Number.isNaN(qty) && resolveUnitPrice(product, qty) !== product.wholesalePrice
    return { product, qty, valid, unitPrice, breakApplied }
  })

  const anyInvalid = rows.some((row) => !row.valid)
  const subtotal = rows
    .filter((row) => row.valid)
    .reduce((sum, row) => sum + row.qty * row.unitPrice, 0)

  return (
    <div className="flex flex-col gap-4">
      <div>
        <h3 className="text-base font-semibold tracking-tight text-stone-900">
          Step 3 — Buyer requests a quote
        </h3>
        <p className="mt-1 text-sm text-stone-600">
          In the buyer portal, Krishna Electricals sets quantities against the
          frozen prices. Quantity minimums and step rules are enforced live.
        </p>
      </div>

      <div className="overflow-x-auto rounded-lg border border-stone-200">
        <table className="w-full text-left text-sm">
          <thead>
            <tr className="border-b border-stone-200 bg-stone-50 text-xs tracking-wide text-stone-500 uppercase">
              <th className="px-4 py-2.5 font-medium">SKU / item</th>
              <th className="py-2.5 font-medium">Qty</th>
              <th className="py-2.5 font-medium">Unit price</th>
              <th className="px-4 py-2.5 text-right font-medium">Line total</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => {
              const { product, qty, valid, unitPrice, breakApplied } = row
              const error = !valid
              return (
                <tr key={product.sku} className="border-b border-stone-100 last:border-b-0">
                  <td className="max-w-[16rem] px-4 py-2.5">
                    <p className="font-mono text-xs text-stone-500">{product.sku}</p>
                    <p className="truncate text-stone-800">{product.name}</p>
                  </td>
                  <td className="py-2.5">
                    {submitted ? (
                      <span className="inline-flex h-9 min-w-14 items-center justify-center rounded-md border border-stone-200 bg-stone-50 px-3 font-mono text-sm text-stone-700">
                        {qty}
                      </span>
                    ) : (
                      <Input
                        type="number"
                        inputMode="numeric"
                        min={product.minQty}
                        step={product.step}
                        value={qtyInput[product.sku] ?? ""}
                        onChange={(event) => onQtyChange(product.sku, event.target.value)}
                        aria-label={`Quantity for ${product.name} (${product.sku})`}
                        aria-invalid={error || undefined}
                        aria-describedby={error ? `qty-error-${product.sku}` : undefined}
                        className={error ? "border-red-400 focus-visible:ring-red-200" : ""}
                      />
                    )}
                    {error && !submitted && (
                      <p
                        id={`qty-error-${product.sku}`}
                        className="mt-1 flex items-center gap-1 text-xs text-red-700"
                      >
                        <AlertCircle className="size-3" aria-hidden="true" />
                        {qtyError(product)}
                      </p>
                    )}
                  </td>
                  <td className="py-2.5">
                    <span className="font-mono text-sm text-stone-700">
                      {formatINR(unitPrice)}
                    </span>
                    {breakApplied && (
                      <Badge
                        variant="outline"
                        className="ml-2 border-teal-200 bg-teal-50 text-xs text-teal-800"
                      >
                        break applied
                      </Badge>
                    )}
                  </td>
                  <td className="px-4 py-2.5 text-right font-mono text-sm font-medium text-teal-800">
                    {valid ? formatINR(qty * unitPrice) : "—"}
                  </td>
                </tr>
              )
            })}
          </tbody>
        </table>
      </div>

      <div className="grid gap-4 sm:grid-cols-[1.4fr_1fr]">
        <Card className="gap-0 border-dashed border-stone-300 bg-stone-50/60 py-0">
          <div className="flex flex-wrap items-center justify-between gap-2 p-5">
            <p className="max-w-56 text-xs leading-relaxed text-stone-500">
              Estimate — tax &amp; shipping resolved by your store&apos;s
              WooCommerce settings
            </p>
            <p className="font-mono text-xl font-semibold text-stone-900">
              {formatINR(subtotal)}
            </p>
          </div>
        </Card>

        <div className="flex flex-col justify-center gap-3">
          {submitted ? (
            <div className="flex flex-col gap-2">
              <Badge
                variant="outline"
                className="w-fit border-teal-200 bg-teal-50 px-3 py-1 text-xs text-teal-800"
              >
                <BadgeCheck aria-hidden="true" />
                Quote request submitted — quantities anchored to the request
              </Badge>
              <p className="text-xs leading-relaxed text-stone-500">
                Quantities are now part of the quote record for{" "}
                <span className="font-mono">
                  {PACKET_ID} · {PACKET_VERSION}
                </span>
                . Restart the demo to change them.
              </p>
            </div>
          ) : (
            <Button
              onClick={onSubmit}
              disabled={anyInvalid}
              className="h-10 bg-teal-700 px-5 text-white hover:bg-teal-800"
            >
              <Send aria-hidden="true" />
              Submit quote request
            </Button>
          )}
        </div>
      </div>
    </div>
  )
}
