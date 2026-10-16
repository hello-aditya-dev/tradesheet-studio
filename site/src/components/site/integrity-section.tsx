import { AlertTriangle, Check, ShieldCheck } from "lucide-react"

import { Badge } from "@/components/ui/badge"
import { Card, CardContent } from "@/components/ui/card"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { Reveal } from "@/components/site/reveal"
import { SectionHeading } from "@/components/site/section-heading"

const FINDINGS = [
  {
    finding: "Price changed +12%",
    detail: "VW-LED-HB150 — quoted ₹4,450.00 → current ₹4,984.00",
    severity: "warning" as const,
    options: ["Honor quoted price", "Propose new price", "Reject"],
  },
  {
    finding: "Product unavailable",
    detail: "VW-CAB-2R5 — removed from catalog since the packet was issued",
    severity: "error" as const,
    options: ["Substitute alternative", "Remove line"],
  },
  {
    finding: "SKU changed",
    detail: "VW-SKT-IEC32 → VW-SKT-IEC32A after a variation restructure",
    severity: "warning" as const,
    options: ["Substitute alternative", "Remove line"],
  },
  {
    finding: "Offer expired",
    detail: "Validity window closed — revision can no longer be accepted",
    severity: "error" as const,
    options: ["Issue new packet version"],
  },
  {
    finding: "Quantity rule violated",
    detail: "VW-MCB-C32 — 37 pcs requested, negotiated price requires multiples of 10",
    severity: "warning" as const,
    options: ["Propose new price", "Remove line"],
  },
] as const

const SEVERITY_STYLES = {
  warning: "border-amber-300 bg-amber-100 text-amber-900",
  error: "border-red-300 bg-red-100 text-red-900",
} as const

const ALL_OPTIONS = [
  "Honor quoted price",
  "Propose new price",
  "Substitute alternative",
  "Remove line",
  "Reject",
] as const

export function IntegritySection() {
  return (
    <section
      id="integrity"
      aria-labelledby="integrity-heading"
      className="scroll-mt-20 border-b border-stone-200/70 bg-white py-16 md:py-24"
    >
      <div className="mx-auto w-full max-w-6xl px-4 sm:px-6 lg:px-8">
        <SectionHeading
          id="integrity-heading"
          eyebrow="Quote integrity engine"
          title="When prices change mid-negotiation, you'll know."
          intro="Between the day you issue a packet and the day the buyer clicks accept, your store keeps moving — prices change, products are retired, SKUs are restructured. The integrity engine compares every quote request against the frozen snapshot and puts each difference on the table before it becomes an order."
        />

        <Reveal>
          <Card className="gap-0 border-stone-200 py-0">
            <CardContent className="p-0">
              <div className="flex flex-wrap items-center justify-between gap-2 border-b border-stone-200 px-6 py-4">
                <p className="flex items-center gap-2 text-sm font-semibold text-stone-900">
                  <AlertTriangle className="size-4 text-amber-600" aria-hidden="true" />
                  Discrepancy report — request on TSS-P-1042 · v1
                </p>
                <p className="text-xs text-stone-400">
                  Stylized report — synthetic demo data
                </p>
              </div>

              <div className="overflow-x-auto">
                <Table>
                  <TableHeader>
                    <TableRow className="hover:bg-transparent">
                      <TableHead className="pl-6 text-stone-500">Finding</TableHead>
                      <TableHead className="text-stone-500">Detail</TableHead>
                      <TableHead className="text-stone-500">Severity</TableHead>
                      <TableHead className="pr-6 text-stone-500">
                        Merchant options
                      </TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {FINDINGS.map((row) => (
                      <TableRow key={row.finding} className="hover:bg-stone-50">
                        <TableCell className="pl-6 font-medium text-stone-900">
                          {row.finding}
                        </TableCell>
                        <TableCell className="text-stone-600">
                          <span className="font-mono text-xs">{row.detail}</span>
                        </TableCell>
                        <TableCell>
                          <Badge
                            variant="outline"
                            className={SEVERITY_STYLES[row.severity]}
                          >
                            {row.severity === "warning" ? "Warning" : "Blocking"}
                          </Badge>
                        </TableCell>
                        <TableCell className="pr-6">
                          <span className="text-xs text-stone-500">
                            {row.options.join(" · ")}
                          </span>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>

              <div className="flex flex-col gap-3 border-t border-stone-200 bg-stone-50/70 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-xs text-stone-500">
                  Full merchant option set:
                </p>
                <ul className="flex flex-wrap gap-2">
                  {ALL_OPTIONS.map((option) => (
                    <li key={option}>
                      <Badge
                        variant="outline"
                        className="border-stone-300 bg-white text-stone-700"
                      >
                        {option}
                      </Badge>
                    </li>
                  ))}
                </ul>
              </div>
            </CardContent>
          </Card>
        </Reveal>

        <Reveal delay={0.1} className="mt-6 md:mt-8">
          <Card className="border-teal-200 bg-teal-50/50 py-0">
            <CardContent className="flex flex-col gap-3 p-6 sm:flex-row sm:items-start">
              <span className="flex size-11 shrink-0 items-center justify-center rounded-lg bg-teal-700 text-white">
                <ShieldCheck className="size-5" aria-hidden="true" />
              </span>
              <div className="space-y-2">
                <p className="text-sm leading-relaxed text-stone-800">
                  A material seller modification never becomes an accepted
                  agreement without explicit buyer acceptance of that revision.
                </p>
                <p className="flex items-start gap-2 text-sm leading-relaxed text-stone-600">
                  <Check className="mt-0.5 size-4 shrink-0 text-teal-700" aria-hidden="true" />
                  Superseded and expired revisions cannot be accepted — the
                  buyer is always committing to a live, approved version.
                </p>
              </div>
            </CardContent>
          </Card>
        </Reveal>
      </div>
    </section>
  )
}
