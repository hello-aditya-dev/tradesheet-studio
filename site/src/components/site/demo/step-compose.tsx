"use client"

import * as React from "react"
import { User } from "lucide-react"

import { Badge } from "@/components/ui/badge"
import { Checkbox } from "@/components/ui/checkbox"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { cn } from "@/lib/utils"
import {
  DEMO_PRODUCTS,
  formatINR,
  savingsPercent,
  type DemoProduct,
} from "@/components/site/demo/demo-data"

function formatBreaks(product: DemoProduct): string {
  if (product.breaks.length === 0) return "—"
  return product.breaks
    .map((tier) => `${tier.qty}+ ${formatINR(tier.price)}`)
    .join(" · ")
}

export function StepCompose({
  selected,
  onToggle,
}: {
  selected: string[]
  onToggle: (sku: string) => void
}) {
  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h3 className="text-base font-semibold tracking-tight text-stone-900">
            Step 1 — Compose the packet
          </h3>
          <p className="mt-1 text-sm text-stone-600">
            Pick the products for Krishna Electricals. Wholesale prices and
            quantity breaks come from the assigned price book.
          </p>
        </div>
        <Badge
          variant="outline"
          className="border-teal-200 bg-teal-50 px-3 py-1.5 text-xs text-teal-800"
        >
          <User aria-hidden="true" />
          Buyer: Krishna Electricals · Price book: Distributor Tier A · Valid 30 days
        </Badge>
      </div>

      <div className="overflow-x-auto rounded-lg border border-stone-200">
        <Table>
          <TableHeader>
            <TableRow className="hover:bg-transparent">
              <TableHead className="w-10 pl-4" aria-label="Include in packet">
                <span className="sr-only">Include in packet</span>
              </TableHead>
              <TableHead className="text-stone-500">SKU</TableHead>
              <TableHead className="text-stone-500">Item</TableHead>
              <TableHead className="text-right text-stone-500">List price</TableHead>
              <TableHead className="text-right text-stone-500">
                Your wholesale price
              </TableHead>
              <TableHead className="pr-4 text-right text-stone-500">Qty breaks</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {DEMO_PRODUCTS.map((product) => {
              const isSelected = selected.includes(product.sku)
              return (
                <TableRow
                  key={product.sku}
                  data-state={isSelected ? "selected" : undefined}
                  className={cn(
                    "transition-colors",
                    isSelected && "bg-teal-50/60",
                    !isSelected && "opacity-60"
                  )}
                >
                  <TableCell className="pl-4">
                    <Checkbox
                      checked={isSelected}
                      onCheckedChange={() => onToggle(product.sku)}
                      aria-label={`Include ${product.name} (${product.sku}) in the packet`}
                      className="border-stone-300 data-[state=checked]:border-teal-700 data-[state=checked]:bg-teal-700"
                    />
                  </TableCell>
                  <TableCell className="font-mono text-xs text-stone-600">
                    {product.sku}
                  </TableCell>
                  <TableCell className="text-sm text-stone-800">
                    {product.name}
                  </TableCell>
                  <TableCell className="text-right font-mono text-sm text-stone-500">
                    {formatINR(product.listPrice)}
                  </TableCell>
                  <TableCell className="text-right">
                    <span className="font-mono text-sm font-semibold text-teal-800">
                      {formatINR(product.wholesalePrice)}
                    </span>
                    {isSelected && (
                      <Badge
                        variant="outline"
                        className="ml-2 border-amber-300 bg-amber-100 text-amber-900"
                      >
                        −{savingsPercent(product)}% vs list
                      </Badge>
                    )}
                  </TableCell>
                  <TableCell className="pr-4 text-right font-mono text-xs text-stone-500">
                    {formatBreaks(product)}
                  </TableCell>
                </TableRow>
              )
            })}
          </TableBody>
        </Table>
      </div>

      <p className="text-xs text-stone-400">
        All prices are synthetic demonstration figures in INR, exclusive of tax.
      </p>
    </div>
  )
}
