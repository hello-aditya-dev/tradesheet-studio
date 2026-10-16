import type { ReactNode } from "react"
import {
  ArrowRight,
  Check,
  Download,
  Lock,
  PencilLine,
  ShieldCheck,
  Upload,
} from "lucide-react"

import { Badge } from "@/components/ui/badge"
import { Card, CardContent } from "@/components/ui/card"
import { Reveal } from "@/components/site/reveal"
import { SectionHeading } from "@/components/site/section-heading"

const CSV_STEPS = [
  {
    icon: Download,
    step: "1",
    title: "Download",
    body: "The buyer gets a CSV from the portal or the packet email — every row carries a durable row ID bound to the packet version.",
  },
  {
    icon: PencilLine,
    step: "2",
    title: "Fill quantities",
    body: "Only quantity fields are meant to change. The buyer edits in Excel, Google Sheets, LibreOffice — whatever they already use.",
  },
  {
    icon: Upload,
    step: "3",
    title: "Upload & verify",
    body: "The completed file goes back through the portal or admin. Every row is validated against the frozen snapshot before anything moves.",
  },
] as const

const VALIDATION_ROWS = [
  {
    row: "VW-LED-HB150 · qty 6",
    status: "matched" as const,
    note: "Matched — price resolved to ₹4,450.00 from snapshot",
  },
  {
    row: "VW-LED-TP36 · qty 24",
    status: "matched" as const,
    note: "Matched — qty break 20+ applied, ₹1,150.00",
  },
  {
    row: "VW-DB-8WAY · qty 2",
    status: "warning" as const,
    note: "File price ₹3,500.00 ignored — packet price is ₹3,890.00",
  },
  {
    row: "VW-SKT-IEC32 · qty 7",
    status: "warning" as const,
    note: "Quantity rule violated — ordered in multiples of 25 for the 25+ price",
  },
  {
    row: "VW-XXX-999 · qty 10",
    status: "error" as const,
    note: "Unknown SKU — not in packet TSS-P-1042 · v1",
  },
] as const

const STATUS_STYLES: Record<(typeof VALIDATION_ROWS)[number]["status"], string> = {
  matched: "border-teal-200 bg-teal-50 text-teal-800",
  warning: "border-amber-200 bg-amber-50 text-amber-800",
  error: "border-red-200 bg-red-50 text-red-800",
}

const STATUS_ICONS: Record<(typeof VALIDATION_ROWS)[number]["status"], ReactNode> = {
  matched: <Check className="size-3" aria-hidden="true" />,
  warning: <span aria-hidden="true" className="text-[10px] font-bold">!</span>,
  error: <span aria-hidden="true" className="text-[10px] font-bold">×</span>,
}

export function CsvSection() {
  return (
    <section
      id="csv"
      aria-labelledby="csv-heading"
      className="scroll-mt-20 border-b border-stone-200/70 bg-white py-16 md:py-24"
    >
      <div className="mx-auto w-full max-w-6xl px-4 sm:px-6 lg:px-8">
        <SectionHeading
          id="csv-heading"
          eyebrow="The CSV round-trip"
          title="Order sheets that come back validated."
          intro="The order sheet is the part of the deal your buyer actually touches. TradeSheet Studio treats the returned file as untrusted input: stable row identifiers map it back to the packet, quantities are read, and everything else is re-resolved from the snapshot."
        />

        <div className="grid gap-4 md:grid-cols-3 md:gap-6">
          {CSV_STEPS.map((step, index) => (
            <Reveal key={step.title} delay={index * 0.06} className="relative h-full">
              <Card className="h-full gap-0 border-stone-200 py-0 transition-shadow duration-300 hover:shadow-md hover:shadow-stone-200/60">
                <CardContent className="flex flex-col gap-3 p-6">
                  <div className="flex items-center justify-between">
                    <span className="flex size-11 items-center justify-center rounded-lg border border-stone-200 bg-stone-50">
                      <step.icon className="size-5 text-stone-700" aria-hidden="true" />
                    </span>
                    <span className="font-mono text-2xl font-semibold text-stone-200">
                      {step.step}
                    </span>
                  </div>
                  <h3 className="text-base font-semibold tracking-tight text-stone-900">
                    {step.title}
                  </h3>
                  <p className="text-sm leading-relaxed text-stone-600">{step.body}</p>
                </CardContent>
              </Card>
              {index < CSV_STEPS.length - 1 && (
                <span
                  aria-hidden="true"
                  className="absolute top-1/2 -right-3 z-10 hidden size-6 -translate-y-1/2 items-center justify-center rounded-full border border-stone-200 bg-white text-stone-400 md:flex"
                >
                  <ArrowRight className="size-3.5" />
                </span>
              )}
            </Reveal>
          ))}
        </div>

        <div className="mt-6 grid gap-4 md:mt-8 md:gap-6 lg:grid-cols-[1.4fr_1fr]">
          <Reveal>
            <Card className="h-full gap-0 border-stone-200 py-0">
              <CardContent className="p-6">
                <p className="mb-4 text-xs font-semibold tracking-wide text-stone-500 uppercase">
                  Upload validation preview · packet TSS-P-1042 · v1
                </p>
                <div className="max-h-80 overflow-y-auto pr-1 [scrollbar-color:#d6d3d1_transparent] [scrollbar-width:thin]">
                  <ul className="flex flex-col gap-2.5">
                    {VALIDATION_ROWS.map((row) => (
                      <li
                        key={row.row}
                        className="flex flex-wrap items-center gap-x-3 gap-y-1.5 rounded-lg border border-stone-200 bg-stone-50/60 px-4 py-3"
                      >
                        <span className="font-mono text-xs text-stone-700">{row.row}</span>
                        <Badge variant="outline" className={`gap-1 ${STATUS_STYLES[row.status]}`}>
                          {STATUS_ICONS[row.status]}
                          {row.status === "matched"
                            ? "OK"
                            : row.status === "warning"
                              ? "Warning"
                              : "Error"}
                        </Badge>
                        <span className="w-full text-xs text-stone-500 sm:w-auto sm:flex-1">
                          {row.note}
                        </span>
                      </li>
                    ))}
                  </ul>
                </div>
                <p className="mt-4 text-xs text-stone-400">
                  Stylized validation preview — synthetic demo data.
                </p>
              </CardContent>
            </Card>
          </Reveal>

          <Reveal delay={0.1} className="h-full">
            <Card className="h-full gap-0 border-amber-200 bg-amber-50/60 py-0">
              <CardContent className="flex flex-col gap-4 p-6">
                <span className="flex size-11 shrink-0 items-center justify-center rounded-lg bg-amber-500 text-white">
                  <ShieldCheck className="size-5" aria-hidden="true" />
                </span>
                <p className="text-base font-semibold tracking-tight text-stone-900">
                  The rule the round-trip runs on
                </p>
                <ul className="flex flex-col gap-3 text-sm leading-relaxed text-stone-700">
                  <li className="flex gap-2.5">
                    <Lock className="mt-0.5 size-4 shrink-0 text-amber-700" aria-hidden="true" />
                    Prices in uploaded files are never trusted — every price is
                    resolved server-side from the packet snapshot.
                  </li>
                  <li className="flex gap-2.5">
                    <Lock className="mt-0.5 size-4 shrink-0 text-amber-700" aria-hidden="true" />
                    Cells are sanitized against spreadsheet-formula injection.
                  </li>
                </ul>
              </CardContent>
            </Card>
          </Reveal>
        </div>
      </div>
    </section>
  )
}
