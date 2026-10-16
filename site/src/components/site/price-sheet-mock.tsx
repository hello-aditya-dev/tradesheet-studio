import * as React from "react"
import { Lock, ShieldCheck, FileText } from "lucide-react"

import { Badge } from "@/components/ui/badge"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"

const SHEET_ROWS = [
  {
    sku: "VW-LED-HB150",
    item: "LED High Bay 150W 5000K",
    breaks: "10+ / 50+",
    price: "₹4,450.00",
  },
  {
    sku: "VW-LED-TP36",
    item: "LED Triproof Batten 36W",
    breaks: "20+",
    price: "₹1,240.00",
  },
  {
    sku: "VW-DB-8WAY",
    item: "Distribution Board 8-Way TPN IP65",
    breaks: "—",
    price: "₹3,890.00",
  },
  {
    sku: "VW-MCB-C32",
    item: "MCB C32 1P 10kA",
    breaks: "50+",
    price: "₹412.00",
  },
] as const

/**
 * An original, stylized CSS mock of a TradeSheet Studio price sheet —
 * an honest representation of the Trade Table document layout, not a
 * screenshot. All figures are synthetic demo data.
 */
export function PriceSheetMock({ className }: { className?: string }) {
  return (
    <div className={className}>
      <div
        aria-hidden="true"
        className="absolute -top-4 -right-3 hidden h-full w-full rounded-2xl border border-stone-200 bg-stone-50 sm:block"
      />
      <div className="relative rounded-2xl border border-stone-200 bg-white shadow-xl shadow-stone-900/5">
        {/* teal document header */}
        <div className="flex items-start justify-between gap-3 rounded-t-2xl bg-teal-700 px-5 py-4 text-white">
          <div className="flex items-center gap-3">
            <span className="flex size-9 items-center justify-center rounded-lg bg-teal-600/60">
              <FileText className="size-5" aria-hidden="true" />
            </span>
            <div>
              <p className="text-sm font-semibold tracking-tight">
                Volt &amp; Wire Trading Co.
              </p>
              <p className="text-xs text-teal-100">
                Wholesale price sheet · Distributor Tier A
              </p>
            </div>
          </div>
          <span className="hidden shrink-0 rounded-md bg-amber-500 px-2 py-1 text-[11px] font-semibold text-amber-950 sm:inline-block">
            Valid 30 days
          </span>
        </div>

        <div className="flex flex-wrap items-center gap-2 border-b border-stone-200 px-5 py-3">
          <Badge
            variant="outline"
            className="border-teal-200 bg-teal-50 text-teal-800"
          >
            DealPacket TSS-P-1042 · v1
          </Badge>
          <Badge
            variant="outline"
            className="border-amber-200 bg-amber-50 text-amber-800"
          >
            <Lock aria-hidden="true" />
            Snapshot frozen · sha256
          </Badge>
        </div>

        <Table>
          <TableHeader>
            <TableRow className="hover:bg-transparent">
              <TableHead className="pl-5 text-stone-500">SKU</TableHead>
              <TableHead className="text-stone-500">Item</TableHead>
              <TableHead className="text-stone-500">Qty breaks</TableHead>
              <TableHead className="pr-5 text-right text-stone-500">
                Buyer price
              </TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {SHEET_ROWS.map((row) => (
              <TableRow key={row.sku} className="hover:bg-stone-50">
                <TableCell className="pl-5 font-mono text-xs text-stone-700">
                  {row.sku}
                </TableCell>
                <TableCell className="text-sm text-stone-800">
                  {row.item}
                </TableCell>
                <TableCell className="text-sm text-stone-500">
                  {row.breaks}
                </TableCell>
                <TableCell className="pr-5 text-right font-mono text-sm font-medium text-teal-800">
                  {row.price}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>

        <div className="flex items-center justify-between gap-3 rounded-b-2xl border-t border-stone-200 bg-stone-50 px-5 py-3">
          <p className="flex items-center gap-1.5 text-xs text-stone-500">
            <ShieldCheck className="size-3.5 text-teal-700" aria-hidden="true" />
            Synthetic demo data — stylized document mock
          </p>
          <p className="font-mono text-xs text-stone-400">INR · excl. GST</p>
        </div>
      </div>
    </div>
  )
}
