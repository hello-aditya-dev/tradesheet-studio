import {
  CreditCard,
  FileDown,
  History,
  KeyRound,
  Search,
  Upload,
} from "lucide-react"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import { Separator } from "@/components/ui/separator"
import { Reveal } from "@/components/site/reveal"
import { SectionHeading } from "@/components/site/section-heading"

const PORTAL_POINTS = [
  {
    icon: KeyRound,
    title: "No account on our website — because there is no our website",
    body: "Access runs through revocable high-entropy tokens (hashed at rest) or the customer's own WooCommerce account. No SaaS middleman.",
  },
  {
    icon: Search,
    title: "Search, filter, and edit quantities",
    body: "The buyer works against the packet: live estimates update as they type, while authoritative quote totals stay clearly separate.",
  },
  {
    icon: FileDown,
    title: "Authorized files, on demand",
    body: "PDFs and CSV order sheets scoped to that packet version — download what they're entitled to, nothing more.",
  },
  {
    icon: Upload,
    title: "Completed CSV uploads",
    body: "Buyers who prefer spreadsheets can upload the filled sheet right where they downloaded it — validation included.",
  },
  {
    icon: History,
    title: "Quote status and acceptance",
    body: "Every revision is visible with its changes. The buyer accepts the approved revision explicitly — superseded or expired ones can't be.",
  },
] as const

export function PortalSection() {
  return (
    <section
      id="portal"
      aria-labelledby="portal-heading"
      className="scroll-mt-20 border-b border-stone-200/70 bg-stone-50 py-16 md:py-24"
    >
      <div className="mx-auto w-full max-w-6xl px-4 sm:px-6 lg:px-8">
        <SectionHeading
          id="portal-heading"
          eyebrow="Protected buyer portal"
          title="Your customer's ordering desk — on your site, not ours."
          intro="Token-gated, no-index, no-cache pages hosted on the merchant's own domain. The buyer gets a private workspace for the offer in play; you keep every byte of the relationship."
        />

        <div className="grid items-start gap-8 lg:grid-cols-2 lg:gap-12">
          <ul className="flex min-w-0 flex-col gap-4 md:gap-5">
            {PORTAL_POINTS.map((point, index) => (
              <li key={point.title}>
                <Reveal delay={index * 0.05}>
                  <div className="flex gap-4">
                    <span className="flex size-11 shrink-0 items-center justify-center rounded-lg border border-teal-200 bg-teal-50">
                      <point.icon className="size-5 text-teal-700" aria-hidden="true" />
                    </span>
                    <div className="min-w-0">
                      <h3 className="text-base font-semibold tracking-tight text-stone-900">
                        {point.title}
                      </h3>
                      <p className="mt-1 text-sm leading-relaxed text-stone-600">
                        {point.body}
                      </p>
                    </div>
                  </div>
                </Reveal>
              </li>
            ))}
          </ul>

          <Reveal delay={0.1} className="min-w-0">
            <div className="overflow-hidden rounded-xl border border-stone-300 bg-white shadow-xl shadow-stone-900/5">
              {/* browser chrome */}
              <div className="flex items-center gap-3 border-b border-stone-200 bg-stone-100 px-4 py-2.5">
                <span aria-hidden="true" className="flex gap-1.5">
                  <span className="size-2.5 rounded-full bg-stone-300" />
                  <span className="size-2.5 rounded-full bg-stone-300" />
                  <span className="size-2.5 rounded-full bg-stone-300" />
                </span>
                <p className="truncate rounded-md border border-stone-200 bg-white px-3 py-1 font-mono text-[10px] text-stone-500">
                  shop.voltandwire.example/dealpacket/TSS-P-1042?token=••••••••••••
                </p>
              </div>

              <div className="flex flex-col gap-4 p-5">
                <div className="flex min-w-0 flex-wrap items-center justify-between gap-2">
                  <div className="min-w-0">
                    <p className="text-sm font-semibold text-stone-900">
                      Volt &amp; Wire Trading Co.
                    </p>
                    <p className="text-xs text-stone-500">
                      DealPacket TSS-P-1042 · v1 · valid until 14 Mar 2026
                    </p>
                  </div>
                  <Badge className="bg-teal-700 text-white">
                    <History aria-hidden="true" />
                    Quote R2 · Approved
                  </Badge>
                </div>

                <div className="flex flex-col divide-y divide-stone-100 rounded-lg border border-stone-200">
                  {[
                    ["VW-LED-HB150 · LED High Bay 150W", "6 × ₹4,450.00", "₹26,700.00"],
                    ["VW-LED-TP36 · LED Triproof Batten 36W", "24 × ₹1,150.00", "₹27,600.00"],
                    ["VW-MCB-C32 · MCB C32 1P 10kA", "60 × ₹385.00", "₹23,100.00"],
                  ].map(([item, qty, total]) => (
                    <div
                      key={item}
                      className="flex items-center justify-between gap-3 px-4 py-2.5"
                    >
                      <p className="min-w-0 flex-1 truncate text-xs text-stone-700">
                        {item}
                      </p>
                      <p className="font-mono text-xs text-stone-500">{qty}</p>
                      <p className="font-mono text-xs font-medium text-teal-800">
                        {total}
                      </p>
                    </div>
                  ))}
                </div>

                <div className="flex items-center justify-between rounded-lg border border-dashed border-stone-300 bg-stone-50 px-4 py-3">
                  <p className="text-xs text-stone-500">
                    Estimate — tax &amp; shipping resolved by your store's
                    WooCommerce settings
                  </p>
                  <p className="font-mono text-sm font-semibold text-stone-900">
                    ₹1,12,329.00
                  </p>
                </div>

                <Separator className="bg-stone-200" />

                <div className="flex flex-wrap gap-2">
                  <Button
                    variant="outline"
                    size="sm"
                    className="h-9 border-stone-300 hover:bg-stone-100"
                  >
                    <FileDown aria-hidden="true" />
                    Download CSV
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    className="h-9 border-stone-300 hover:bg-stone-100"
                  >
                    <Upload aria-hidden="true" />
                    Upload completed CSV
                  </Button>
                  <Button
                    size="sm"
                    className="h-9 bg-teal-700 text-white hover:bg-teal-800"
                  >
                    Accept revision R2
                  </Button>
                </div>
              </div>
            </div>
            <p className="mt-3 text-center text-xs text-stone-500">
              Stylized portal mock — synthetic demo data. Buttons are
              illustrative, not functional.
            </p>

            <Card className="mt-4 gap-0 border-stone-200 py-0">
              <CardContent className="flex items-start gap-3 p-5">
                <CreditCard className="mt-0.5 size-5 shrink-0 text-amber-600" aria-hidden="true" />
                <p className="text-sm leading-relaxed text-stone-600">
                  Payment happens through the merchant's normal WooCommerce
                  checkout and gateways.{" "}
                  <strong className="font-semibold text-stone-800">
                    The plugin never charges anyone.
                  </strong>
                </p>
              </CardContent>
            </Card>
          </Reveal>
        </div>
      </div>
    </section>
  )
}
